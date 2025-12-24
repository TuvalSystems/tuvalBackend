<?php

namespace App\Http\Controllers\devoluciones;

use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\Controller;
use App\Jobs\Devoluciones\NotifyErrorAnulationJob;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DevolucionesController extends Controller
{
    use JsonResponseTrait;
    protected $entryService;
    protected $devolucionService;
    protected $notificationService;
    public function __construct(
        EntryServiceInterface $entryService,
        DevolucionServiceInterface $devolucionServiceInterface,
    ) {
        $this->entryService = $entryService;
        $this->devolucionService = $devolucionServiceInterface;
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

    public function detallepedidodevolucion(Request $request)
    {
        try {
            $data = $request->all();
            $info = $this->devolucionService->getDetailOrderReturnById($data);
            return response([
                'success' => true,
                'detalle' => $info['detalle'],
                'motivos' => $info['motivos'],
                'clientes' => $info['clientes'],
                'devolucion_anterior' => $info['devolucion_anterior'],
                'items_devueltos' => $info['items_devueltos'],
            ]);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }


    //procesos nuevos comentados para no afectar produccion en espera de aprobacion de Dennis para deploy a producccion
    //funcion en estado pendiente de utilizar (refactorizado el proceso de recibir devoluciones en el controller, utilizo services y repositorios )
    // public function enviardevolucionOrigin(Request $request)
    // {

    //     try {
    //         $data = $request->all();
    //         DB::beginTransaction();
    //         // 🔎 **1️⃣ Validación de Pedido**
    //         $pedido = GpPedido::find($data['pedido']['id']);

    //         if (!$pedido) {
    //             return response(['message' => 'Pedido no encontrado'], 404);
    //         }
    //         if ($pedido->despacho_en_proceso && !$pedido->despachado) {
    //             return response(['message' => 'Este pedido está siendo despachado'], 401);
    //         }

    //         // 🔎 **2️⃣ Inicialización de la estructura agrupada**
    //         $detallePedidoAgrupado = [
    //             'devolucion' => $data['devolucion'],
    //             'pedido' => $data['pedido'],
    //             'detallepedido' => []
    //         ];


    //         // 🔄 **3️⃣ Filtrado de detalles válidos**
    //         foreach ($data['detallepedido'] as $detalle) {
    //             $isValid = $data['devolucion']['volver_a_facturar'] || $detalle['seleccionado'] > 0;
    //             if ($isValid) {
    //                 //se verifica que este despachado y asignamos el estado al pedido
    //                 $detallePedidoAgrupado['detallepedido'][] = $detalle;
    //             }
    //         }

    //         // 🔄 **4️⃣ Verificación de despacho**
    //         $countDetalle = count(array_filter($data['detallepedido'], function ($detalle) {
    //             return isset($detalle['despacho']) && $detalle['despacho'] === true && $detalle['despachado'] > 0;
    //         }));

    //         $detallePedidoAgrupado['pedido']['despachado'] = $countDetalle > 0 ? 1 : 0;

    //         // 🔄 **5️⃣ Ejecución de la devolución**
    //         if (count($detallePedidoAgrupado['detallepedido']) > 0) {
    //             $devolucion = $this->devolucionService->executeReturn($detallePedidoAgrupado);
    //         }


    //         $order = app(OrderServiceInterface::class);
    //         $dispatch = app(DispatchServiceInterface::class);
    //         $logistics = app(LogisticsOperationsServiceInterface::class);
    //         $despachos = new DespachosController($order, $dispatch, $logistics);
    //         $despachos->checkeliminardespacho($pedido);

    //         // se movio la anulacion para tomar el id de la devolución para enviar a anular la factura
    //         $validateAnulationInvoiceDispatch = $this->devolucionService->validateInvoiceNotDispatchOrInProcess($data['pedido']);
    //         $countsReturnsByOrders = $this->devolucionService->countsReturnsByOrders($data['pedido'], $devolucion);
    //         $validateItemsL = $this->devolucionService->validateItemLDetailInvoice($devolucion);
    //         $validateItemsPartialDispatch = $this->devolucionService->validateItemPartialDispatch($devolucion);
    //         if (
    //             $validateAnulationInvoiceDispatch &&
    //             ($data['devolucion']['tipo_devolucion'] ?? '') === "total" &&
    //             !$countsReturnsByOrders &&
    //             !$validateItemsL &&
    //             !$validateItemsPartialDispatch &&
    //             ($data['devolucion']['volver_a_facturar'] ?? false) === false &&
    //             ($devolucion->permite_anulacion === true || $devolucion->permite_anulacion === true)
    //         ) {
    //             $anulacionResponse = $this->devolucionService->anularDevolucion($data['pedido'], $devolucion);
    //             if (!$anulacionResponse || !$anulacionResponse->getData(true)) {
    //                 $responseArray = $anulacionResponse->getData(true);
    //                 if ($responseArray['success'] == false) {
    //                     $error = $anulacionResponse->getData(true)['error'] ?? 'A ocurrido un error al anular la factura';
    //                     return $this->buildErrorResponse($error, 401);
    //                 }
    //             }
    //         }
    //         DB::commit();
    //         $esSolicitada = $devolucion->estado === 'solicitado';
    //         $esTotal = $devolucion->tipo_devolucion === 'total';
    //         $devolucionCodigo = is_object($devolucion) ? $devolucion->devolucion : '';
    //         if ($esSolicitada && !$devolucion->permite_anulacion && $esTotal) {
    //             return $this->buildSuccessResponse(
    //                 "La devolución {$devolucionCodigo} ha sido solicitada. no se podrá anular la factura ya que tiene pagos registrados. La devolución será procesada normalmente.",
    //                 200
    //             );
    //         }


    //         if ($esSolicitada && !$devolucion->anulacion_habilitada && $esTotal && !$devolucion->permite_anulacion) {
    //             return $this->buildSuccessResponse(
    //                 "La devolución {$devolucionCodigo} ha sido solicitada, pero no se procesará porque excede el límite de días permitido para realizar anulaciones. La devolución sera procesada normalmente.",
    //                 200
    //             );
    //         }

    //         return $this->buildSuccessResponse('La devolución ' . (is_object($devolucion) ? $devolucion->devolucion : '') . ' ha sido solicitada. ', 200);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         $userId = optional(Auth::user())->id ?? ($data['pedido']['user_id'] ?? null);
    //         NotifyErrorAnulationJob::dispatch($userId, $th->getMessage());
    //         Log::channel('devolucion')->info("Error en la funcion enviardevolucion: {$th->getMessage()} - Devolución ID: {$devolucion->id}");
    //         return $this->buildErrorResponse($th->getMessage(), 401);
    //     }
    // }

    public function enviarDevolucion(Request $request): JsonResponse
    {
        $data = $request->all();
        $userId = optional(Auth::user())->id ?? ($data['pedido']['user_id'] ?? null);
        $gpUser = GpUsuario::where('id', $userId)->first();
        try {
            $newDev = $this->devolucionService->handleDevolutionTransaction($data);
            $statusDev = $newDev->estado === 'solicitado';
            $tipoDev = $newDev->tipo_devolucion === 'total';
            $devCode = is_object($newDev) ? $newDev->devolucion : '';
            if (isset($newDev->motivo_no_anulada) && $newDev->estado = 'no_procesado') {
                // NotifyErrorAnulationJob::dispatch($userId, $newDev->warning_message);
                $gpUser->notify(new UserNotification('Devolucion No Procesada', $newDev->warning_message));
                return $this->buildSuccessResponse(
                    "ATENCIÓN: " . $newDev->motivo_no_anulada,
                    200
                );
            }
            if ($statusDev && $tipoDev) {
                // Caso: Tiene pagos registrados
                if (!$newDev->permite_anulacion) {
                    return $this->buildSuccessResponse(
                        "La devolución {$devCode} ha sido solicitada. No se podrá anular la factura ya que tiene pagos registrados. La devolución será procesada normalmente.",
                        200
                    );
                }

                if (!$newDev->anulacion_habilitada) {
                    return $this->buildSuccessResponse(
                        "La devolución {$devCode} ha sido solicitada, pero no se procesará porque excede el límite de días permitido para anulaciones. La devolución será procesada normalmente.",
                        200
                    );
                }
            }
            return $this->buildSuccessResponse("La devolución  {$devCode} ha sido solicitada. ", 200);

        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            $logId = isset($newDev) ? $newDev->id : 'No creado';
            $msgError = isset($newDev) ? $newDev->motivo_no_anulada : $th->getMessage();
            Log::channel('devolucion')->info("Error al Procesar la devolución: {$th->getMessage()} - Devolución ID: {$logId}");
            $gpUser->notify(new UserNotification('Error al Procesar la devolucion', $msgError));
            return $this->buildErrorResponse($th->getMessage(), 500);
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

    public function listaDevolucionesPorRecibir(Request $request): JsonResponse
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaId = $request->input('ptoVtaId', null);

        try {
            $data = $this->devolucionService->fetchReturnsToReceived($perPage, $search, $sortBy, $sortOrder, $ptoVtaId);
            return $this->successResponse('Lista de devoluciones por recibir obtenida correctamente', $data, 200);

        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la lista de devoluciones por recibir: ' . $e->getMessage(), [], 500);
        }
    }


    public function recibirdevolucion(Request $request)
    {

        $user = Auth::user();
        $data = $request->all();
        try {
            $devolucion = GpDevoluciones::find($data['devolucion']['id']);
            if (!$devolucion) {
                abort(404, 'devolucion no encontrada');
            }

            if (in_array($devolucion->estado, ['recibido', 'procesado'])) {
                abort(404, 'Esta devolución ya fue recibida en otra instancia');
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
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            $devolucionId = data_get($data, 'devolucion.id', 'DESCONOCIDO');
            Log::channel('devolucion')->error("Error al recibir devolución ID {$devolucionId}", [
                'exception' => $th,
                'error_summary' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'data' => $data,
            ]);
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

    public function restaurarNotaCreditoDevolucion($id)
    {
        return $this->devolucionService->responseCreditNoteDevolution($id);
    }
}
