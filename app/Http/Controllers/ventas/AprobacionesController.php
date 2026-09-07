<?php

namespace App\Http\Controllers\ventas;

use App\Enums\devoluciones\DevolutionStatus;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpOrdenDespacho;
use App\Models\GpClienteSucursal;
use App\Http\Controllers\Controller;
use App\Models\devoluciones\GpDevoluciones;
use App\Services\Api\ApiGpService;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AprobacionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;


    public function __construct(
        protected readonly ApiGpService $apiGpService,
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly ApprovalOrderServiceInterface $approvalOrder,
        protected readonly ApprovalQuotationServiceInterface $approvalQuote

    ) {}
    public function estadoCuentaCliente(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'                  => 'required|integer|exists:gp_pedidos,id',
            ]);
            $id = data_get($validate, 'id');
            $respuesta = $this->approvalOrder->accountStatusClient($id);
            return $this->successResponse('Información Obtenida Correctamente', $respuesta, 200);
        }, 'laravel', 'estadoCuentaCliente');
    }
    public function listaCotizacionesPendientesPorAprobar(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'puntodeventa_id' => 'required',
            ]);
            $ptovta   = data_get($validate, 'puntodeventa_id');
            $data     = $this->approvalQuote->getAllQuotesPendingApprovals($ptovta, $request->user());
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'AprobacionesController.listaCotizacionesPendientesPorAprobar');
    }
    public function listaCotizacionesPendientesConsulta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'puntodeventa_id' => 'required',
            ]);
            $ptovta   = data_get($validate, 'puntodeventa_id');
            $data     = $this->approvalQuote->getAllQuotesForGlobalConsultation($ptovta);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'AprobacionesController.listaCotizacionesPendientesConsulta');
    }

    public function listaPedidosPendientesPorAprobar(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'puntodeventa_id' => 'required',
            ]);
            $ptovta   = data_get($validate, 'puntodeventa_id');
            $data = $this->approvalOrder->getAllOrdersPendingApprovals($ptovta, $request->user());
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'AprobacionesController.listaPedidosPendientesPorAprobar');
    }

    public function listaPedidosPendientesConsulta(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate  = $request->validate([
                'puntodeventa_id' => 'required',
            ]);
            $ptovta   = data_get($validate, 'puntodeventa_id');
            $data     = $this->approvalOrder->getAllOrdersForGlobalConsultation($ptovta);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'AprobacionesController.listaPedidosPendientesConsulta');
    }


    public function verificarStockPedidoParaAprobacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id' => 'required|integer|exists:gp_pedidos,id',
            ]);
            $id     = data_get($validate, 'id');
            $alert  =  $this->approvalOrder->validateOrderStockForApproval($id);
            if ($alert) {
                return $this->buildExistResponse(false, $alert, 200);
            }
            return $this->buildExistResponse(true, 'La reserva cuenta con Stock Disponible', 200);
        }, 'laravel', 'consultaVerificacionStockPedido');
    }



    public function aprobacionComercialPedido(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'    => 'required|integer|exists:gp_pedidos,id',
                'msg'   => 'nullable|string'
            ]);
            $id         = data_get($validate, 'id');
            $msg        = data_get($validate, 'msg');
            $user       = $request->user();
            $pedido     = DB::transaction(function () use ($id, $user, $msg) {
                return $this->approvalOrder->approveComercialOrder($id, $user, $msg);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} aprobado", 200);
        }, 'laravel', 'aprobarpedido');
    }


    public function rechazarAprobacionComercialPedido(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'   => 'required|integer|exists:gp_pedidos,id',
                'msg'  => 'nullable|string'
            ]);
            $user       = $request->user();
            $orderId    = data_get($validate, 'id');
            $alert      = data_get($validate, 'msg');
            $pedido     = DB::transaction(function () use ($orderId, $user, $alert) {
                return $this->approvalOrder->rejectCommercialOrder($orderId,  $user, $alert,);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} rechazado", 200);
        }, 'laravel', 'rechazarpedido');
    }

    public function rechazarAprobacionComercialPedidoStock(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'    => 'required|integer|exists:gp_pedidos,id',
                'msg'   => 'nullable|string'
            ]);
            $orderId    = data_get($validate, 'id');
            $alert      = data_get($validate, 'msg');

            $gpuser = $request->user();
            $order = DB::transaction(function () use ($orderId, $alert, $gpuser) {
                return $this->approvalOrder->rejectCommercialOrder($orderId,   $gpuser, $alert);
            });

            return $this->buildSuccessResponse("Pedido {$order->pedido} rechazado por: {$alert}", 200);
        }, 'laravel', 'rechazarpedidoporstock');
    }





    public function aprobarpedidocrediticio(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'    => 'required|integer|exists:gp_pedidos,id',
                'msg'   => 'nullable|string'
            ]);
            $orderId    = data_get($validate, 'id');
            $mensaje    = data_get($validate, 'msg');
            $gpuser     = $request->user();
            $order      = DB::transaction(function () use ($orderId, $gpuser, $mensaje) {
                return $this->approvalOrder->approveCreditOrder($orderId, $gpuser, $mensaje);
            });
            return $this->buildSuccessResponse("Pedido {$order->pedido} aprobado", 200);
        }, 'laravel', 'aprobarpedidocrediticio');
    }

    public function rechazarAprobacionCrediticiaPedido(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'    => 'required|integer|exists:gp_pedidos,id',
                'msg'   => 'nullable|string'
            ]);
            $orderId    = data_get($validate, 'id');
            $alert      = data_get($validate, 'msg');
            $user       = $request->user();
            $order     = DB::transaction(function () use ($orderId, $user, $alert) {
                return $this->approvalOrder->rejectCreditOrder($orderId, $user, $alert);
            });
            return $this->buildSuccessResponse("Pedido {$order->pedido} rechazado", 200);
        }, 'laravel', 'rechazarPedidoCrediticio');
    }

    public function rechazarAprobacionCrediticiaPedidoStock(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id'    => 'required|integer|exists:gp_pedidos,id',
                'msg'   => 'nullable|string'
            ]);
            $orderId    = data_get($validate, 'id');
            $alert      = data_get($validate, 'msg');
            $user       = $request->user();
            $order = DB::transaction(function () use ($orderId, $user, $alert) {
                return $this->approvalOrder->rejectCreditOrder($orderId,  $user, $alert);
            });

            return $this->buildSuccessResponse("Pedido {$order->pedido} rechazado por: {$alert}", 200);
        }, 'laravel', 'rechazarPedidoCrediticioPorStock');
    }



    public function aprobacionComercialCotizacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->all();
            $user = $request->user();
            $quote = DB::transaction(fn() => $this->approvalQuote->approveComercialQuote($data['id'], $user));
            return $this->buildSuccessResponse("Cotización {$quote->cotizacion} aprobada", 200);
        }, 'laravel', ' aprobacionComercialCotizacion');
    }
    public function rechazarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = $request->user();

        if (GpCotizacion::where('id', $data['id'])->update(
            [
                'aprobado_comercial'    => 'no',
                'user_rechazo_id'       => $user->id,
                'estado'                => 'rechazado',
                'fe_rechazo'            => now()->format('Y-m-d H:i:s'),
            ]
        )) {
            return response([
                'success' => true,
                'message' => 'Cotización ' . $data['cotizacion'] . ' rechazada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }




    public function aprobardevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = $request->user();

        $devolucion = GpDevoluciones::where('id', $data['id'])->first();

        $devolucion->aprobado = 'yes';
        $devolucion->estado = DevolutionStatus::APROBADO;

        if ($devolucion->volver_a_facturar == 1 && $devolucion->despachado == 1) {
            //$devolucion->estado = 'recibido';
        }

        $devolucion->aprobador_id = $user->id;
        if ($devolucion->save()) {


            return response([
                'success' => true,
                'message' => 'Devolucion ' . $data['devolucion'] . ' aprobada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }

    public function rechazardevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = $request->user();

        $devolucion = GpDevoluciones::where('id', $data['id'])->first();
        $devolucion->aprobado = 'yes';
        $devolucion->estado = DevolutionStatus::RECHAZADO;
        $devolucion->aprobador_id = $user->id;
        if ($devolucion->save()) {

            return response([
                'success' => true,
                'message' => 'Devolucion ' . $data['devolucion'] . ' rechazada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 409);
        }
    }






    public function verdevolucionesporaprobar(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $devoluciones = GpDevoluciones::join('gp_clientes', 'gp_clientes.id', 'gp_devoluciones.cliente_id')
            ->join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->join('gp_punto_ventas', 'gp_pedidos.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_ID')
            ->select('gp_devoluciones.*', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC', 'gp_punto_ventas.PTO_VTA_DESCRIPCION', 'gp_pedidos.fecha_factura')
            ->where('requiere_aprobacion', 'yes')
            ->where('aprobado', 'no')
            ->where('gp_devoluciones.estado', '=', 'espera')
            ->orderBy('created_at', 'DESC')->get();

        foreach ($devoluciones as $devolucion) {

            $devolucion->orden_despacho = false;
            $orden_despacho = GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)
                ->whereIn('estado', ['liberada', 'despachada'])
                ->first();
            if ($orden_despacho)
                $devolucion->orden_despacho = true;

            $devolucion->cliente = GpCliente::where('id', $devolucion->cliente_id)->first();
            if ($devolucion->volver_a_facturar) {
                $devolucion->cliente_volver_a_facturar = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
                $devolucion->sucursal_volver_a_facturar = GpClienteSucursal::where('id', $devolucion->sucursal_facturacion_id)->first();
            }
        }

        return response([
            'success' => true,
            'devoluciones' => $devoluciones,
        ]);
    }


    /** proceso nuevo de rechazar cotizacion cuando esta en espera */
    public function recharCotizacionEstadoEspera(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $id = data_get($request->all(), 'id');
            $quote = $this->approvalQuote->denyQuotationBeenWaiting($id, $request->user());
            return $this->successResponse('Cotización rechazada', $quote, 200);
        }, 'laravel', 'rechazarCotizacionEstadoEspera');
    }

    public function rechazarPedidoEstadoEspera(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $id = data_get($request->all(), 'id');
            $order = $this->approvalOrder->denyOrderBeenWaiting($id, $request->user());
            return $this->successResponse('Pedido rechazado', $order, 200);
        }, 'laravel', 'rechazarPedidoEstadoEspera');
    }

    /** lista de pedidos pendientes de aprobación crediticia */
    public function listaPedidosPendientesAprobacionCrediticia(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->input('filters', []);
            $lista = $this->approvalOrder->fetchOrdersPendingCreditApproval($filters);
            return $this->successResponse('Lista Obtenida', $lista, 200);
        }, 'laravel', 'listaPedidosPendientesAprobacionCrediticia');
    }
}
