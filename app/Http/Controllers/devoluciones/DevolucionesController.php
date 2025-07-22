<?php

namespace App\Http\Controllers\devoluciones;

use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\Controller;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionesController extends Controller
{
    use JsonResponseTrait;
    protected $entryService;
    protected $devolucionService;
    protected $notificationService;
    public function __construct(
        EntryServiceInterface $entryService,
        DevolucionServiceInterface $devolucionServiceInterface,
        NotificationServiceInterface $notificationServiceInterface
    ) {
        $this->entryService = $entryService;
        $this->devolucionService = $devolucionServiceInterface;
        $this->notificationService = $notificationServiceInterface;
    }


    public function pedidoscliente($cliente_id, $puntodeventa_id)
    {
        $pedidos = GpPedido::with('direccion_entrega')->where('PTO_VTA_ID', $puntodeventa_id)
            ->where('cliente_id', $cliente_id)
            ->where(function ($query) {
                $query->where('estado', 'facturado')
                    ->orWhere('estado', 'anulada');
            })
            ->orderBy('created_at', 'desc')->limit(10000)->get();

        $ordenesDespachoPedidos = GpOrdenDespacho::whereIn('pedido_id', $pedidos->pluck('id'))
            ->where('estado', '!=', 'pendiente')
            ->pluck('pedido_id')->toArray();

        foreach ($pedidos as $pedido) {
            $pedido->orden_despacho = in_array($pedido->id, $ordenesDespachoPedidos);
        }

        $devoluciones = GpDevoluciones::with(['cliente', 'cliente_volver_a_facturar', 'sucursal_volver_a_facturar',])
            ->where('cliente_id', $cliente_id)
            ->whereHas('pedido', function ($query) use ($puntodeventa_id) {
                $query->where('PTO_VTA_ID', $puntodeventa_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $ordenesDespachoDevoluciones = GpOrdenDespacho::whereIn('pedido_id', $devoluciones->pluck('pedido_id'))
            ->where('estado', '!=', 'pendiente')
            ->pluck('pedido_id')
            ->toArray();
        foreach ($devoluciones as $devolucion) {
            $devolucion->orden_despacho = in_array($devolucion->pedido_id, $ordenesDespachoDevoluciones);
        }

        return response([
            'success' => true,
            'pedidos' => $pedidos,
            'devoluciones' => $devoluciones,
        ]);
    }

    public function detallepedidodevolucion()
    {
        $data = request()->all();
        return $this->devolucionService->getDetailOrderReturnById($data);

    }


    //procesos nuevos comentados para no afectar produccion en espera de aprobacion de Dennis para deploy a producccion
    //funcion en estado pendiente de utilizar (refactorizado el proceso de recibir devoluciones en el controller, utilizo services y repositorios )
    public function enviardevolucion(Request $request)
    {

        try {
            $data = $request->all();
            DB::beginTransaction();
            // 🔎 **1️⃣ Validación de Pedido**
            $pedido = GpPedido::find($data['pedido']['id']);

            if (!$pedido) {
                return response(['message' => 'Pedido no encontrado'], 404);
            }
            if ($pedido->despacho_en_proceso && !$pedido->despachado) {
                return response(['message' => 'Este pedido está siendo despachado'], 401);
            }

            // 🔎 **2️⃣ Inicialización de la estructura agrupada**
            $detallePedidoAgrupado = [
                'devolucion' => $data['devolucion'],
                'pedido' => $data['pedido'],
                'detallepedido' => []
            ];


            // 🔄 **3️⃣ Filtrado de detalles válidos**
            foreach ($data['detallepedido'] as $detalle) {
                $isValid = $data['devolucion']['volver_a_facturar'] || $detalle['seleccionado'] > 0;
                if ($isValid) {
                    //se verifica que este despachado y asignamos el estado al pedido
                    $detallePedidoAgrupado['detallepedido'][] = $detalle;
                }
            }

            // 🔄 **4️⃣ Verificación de despacho**
            $countDetalle = count(array_filter($data['detallepedido'], function ($detalle) {
                return isset($detalle['despacho']) && $detalle['despacho'] === true && $detalle['despachado'] > 0;
            }));

            $detallePedidoAgrupado['pedido']['despachado'] = $countDetalle > 0 ? 1 : 0;

            // 🔄 **5️⃣ Ejecución de la devolución**
            if (count($detallePedidoAgrupado['detallepedido']) > 0) {
                $devolucion = $this->devolucionService->executeReturn($detallePedidoAgrupado);
            }


            $order = app(OrderServiceInterface::class);
            $dispatch = app(DispatchServiceInterface::class);
            $despachos = new DespachosController($order, $dispatch);
            $despachos->checkeliminardespacho($pedido);

            // se movio la anulacion para tomar el id de la devolución para enviar a anular la factura
            $validateAnulationInvoiceDispatch = $this->devolucionService->validateInvoiceNotDispatchOrInProcess($data['pedido']);
            $countsReturnsByOrders = $this->devolucionService->countsReturnsByOrders($data['pedido'], $devolucion);
            $validateItemsL = $this->devolucionService->validateItemLDetailInvoice($devolucion);
            $validateItemsPartialDispatch = $this->devolucionService->validateItemPartialDispatch($devolucion);
            $validateBeAnulationInvoiceByLimitDate = $this->devolucionService->canBeAnulledByLimitDate($data['pedido']);
            if (
                $validateAnulationInvoiceDispatch &&
                isset($data['devolucion']['tipo_devolucion']) &&
                $data['devolucion']['tipo_devolucion'] === "total" &&
                $countsReturnsByOrders === 0 &&
                $validateItemsL === 0 &&
                $validateItemsPartialDispatch === 0 &&
                $data['devolucion']['volver_a_facturar'] === false &&
                ($devolucion->permite_anulacion === true || $devolucion->permite_anulacion === 1)
                && $validateBeAnulationInvoiceByLimitDate
            ) {
                $anulacionResponse = $this->devolucionService->anularDevolucion($data['pedido'], $devolucion);
                if (!$anulacionResponse || !$anulacionResponse->getData(true)) {
                    $responseArray = $anulacionResponse->getData(true);
                    if ($responseArray['success'] == false) {
                        $error = $anulacionResponse->getData(true)['error'] ?? 'A ocurrido un error al anular la factura';
                        return $this->buildErrorResponse($error, 401);
                    }
                }
            }
            DB::commit();
            if ($devolucion->estado === 'solicitado' && ($devolucion->permite_anulacion === false || $devolucion->permite_anulacion === 0) && $devolucion->tipo_devolucion === 'total') {
                return $this->buildSuccessResponse('La devolución ' . (is_object($devolucion) ? $devolucion->devolucion : '') . ' ha sido solicitada. Pero se procesara la devolución sin anular la factura porque tiene pagos aplicados', 200);
            }
            return $this->buildSuccessResponse('La devolución ' . (is_object($devolucion) ? $devolucion->devolucion : '') . ' ha sido solicitada. ', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            $userId = optional(Auth::user())->id ?? ($data['pedido']['user_id'] ?? null);
            $this->notificationService->sendNotification(
                $userId,
                'Error al anular la factura',
                $th->getMessage()
            );
            return $this->buildErrorResponse($th->getMessage(), 401);
        }
    }

    public function detalledevolucion(Request $request)
    {
        $data = $request->all();
        return $this->devolucionService->getReturnsDetailDevolucion($data);
    }


    public function verdevolucionesporrecibir($puntodeventa): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;
        $devoluciones = GpDevoluciones::join('gp_clientes', 'gp_clientes.id', 'gp_devoluciones.cliente_id')
            ->join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->join('gp_punto_ventas', 'gp_pedidos.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_ID')
            ->where(function ($query) {
                $query->where('gp_devoluciones.estado', 'aprobado');
                $query->orWhere('gp_devoluciones.estado', 'solicitado');
            })
            ->where('gp_devoluciones.despachado', 1)
            ->where('gp_pedidos.BOD_BODEGA', $bodega)
            ->select('gp_devoluciones.*', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
            ->get();

        //        ->where('volver_a_facturar',1)
        //        ->where('tipo_devolucion', 'total')

        foreach ($devoluciones as $key => $devolucion) {

            if ($devolucion->volver_a_facturar == 1 && $devolucion->tipo_devolucion == 'total') {
                unset($devoluciones[$key]);
            }

            $devolucion->orden_despacho = false;
            $orden_despacho = GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)
                ->where('estado', '!=', 'pendiente')
                ->first();
            if ($orden_despacho)
                $devolucion->orden_despacho = true;

            $devolucion->cliente = GpCliente::where('id', $devolucion->cliente_id)->first();
            if ($devolucion->volver_a_facturar) {
                $devolucion->cliente_volver_a_facturar = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
                $devolucion->sucursal_volver_a_facturar = GpClienteSucursal::where('id', $devolucion->sucursal_facturacion_id)->first();
            }
        }

        $devoluciones = $devoluciones->toArray();
        $devoluciones = array_values($devoluciones);

        return response([
            'success' => true,
            'devoluciones' => $devoluciones,
        ]);
    }


    public function recibirdevolucion(Request $request)
    {

        $user = Auth::user();
        $data = $request->all();
        try {
            $devolucion = GpDevoluciones::find($data['devolucion']['id']);
            if (!$devolucion) {
                return $this->buildErrorResponse('devolucion no encontrada', 404);
            }

            if (in_array($devolucion->estado, ['recibido', 'procesado'])) {
                return $this->buildErrorResponse('Esta devolución ya fue recibida en otra instancia', 404);
            }

            DB::transaction(function () use ($data, $user, $devolucion) {
                foreach ($data['detalledevolucion'] as $detalle) {
                    GpDevolucionesDetalle::where('id', $detalle['id'])->update([
                        'devuelto' => $detalle['devuelto'],
                        'recibido' => $detalle['devuelto'],
                        'compartimiento' => $detalle['compartimiento'],
                        'USERID' => $user->USERID
                    ]);
                }
                $devolucion->update(['estado' => 'recibido']);
                $this->entryService->registerEntity('devolucion', $data['devolucion'], $data['detalledevolucion']);
            });

            return $this->buildSuccessResponse('Devolución recibida', 200);
        } catch (\Throwable $th) {
            Log::error("Error al recibir devolución ID {$data['devolucion']['id']}: {$th->getMessage()}");
            return $this->buildErrorResponse('Error al recibir la devolución ' . $th->getMessage(), 500);
        }
    }


    public function imprimirdevolucion($id)
    {
        $devolucion = $this->devolucionService->printReturn($id);
        $pdf = PDF::loadView('devolucionPDF', $devolucion);
        return $pdf->stream('nota_de_credito.pdf');
    }



    /**
     * PRUEBAS PARA GENERACION DE NOTA DE CREDITO --web
     */

    public function generarNotaCreditoDevolucionesFallidas($id)
    {
        return $this->devolucionService->generarNotaCreditoDevolucionesFallidas($id);
    }

    public function generarAjusteDevolucionesFallidas($id)
    {
        return $this->devolucionService->generarAjusteDevolucionesVentaFallidas($id);
    }

    public function restaurarNotaCreditoDevolucion($id){
        return $this->devolucionService->responseCreditNoteDevolution($id);
    }

}
