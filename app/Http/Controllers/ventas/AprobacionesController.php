<?php

namespace App\Http\Controllers\ventas;

use App\Enums\devoluciones\DevolutionStatus;
use App\Enums\Ventas\OrderStatus;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpOrdenDespacho;
use App\Models\GpClienteSucursal;
use App\Http\Controllers\Controller;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpUsuario;
use App\Services\Api\ApiGpService;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AprobacionesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;


    public function __construct(
        protected readonly ApiGpService $apiGpService,
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly ApprovalOrderServiceInterface $approvalOrder,
        protected readonly ApprovalQuotationServiceInterface $approvalQuote

    ) {}
    public function estadodecuenta(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $detalle = $this->approvalService->accountStatusClient($data['CLI_RUC']);
            return $this->successResponse('Información Obtenida Correctamente', $detalle, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al obtener la información: ' . $e->getMessage(), 500);
        }
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



    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGpService->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }


    public function verpedidosporaprobarcrediticios(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([
            'puntodeventa_id' => 'required',
        ]);
        $pedidos = GpPedido::where('requiere_aprobacion_crediticia', 'yes')
            ->where('aprobado_crediticio', 'no')
            ->where(function ($q) {
                $q->where('requiere_aprobacion_comercial', 'no')
                    ->orWhere('requiere_aprobacion_comercial', 'yes')
                    ->where('aprobado_comercial', 'yes');
            })
            // ->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado', 'facturado', 'enviando', 'procesado'])
            ->whereNotIn('estado', [
                OrderStatus::ELIMINADO,
                OrderStatus::ANULADA,
                OrderStatus::RECHAZADO,
                OrderStatus::FACTURADO,
                OrderStatus::ENVIANDO,
                OrderStatus::PROCESADO,
                // Agrega los demás casos si existen en tu Enum
            ])
            ->orderBy('created_at', 'DESC')->get();

        foreach ($pedidos as $key => $pedido) {
            $res = $this->getSegmentoCliente($pedido->CLI_RUC);
            $pedido->segmento = data_get($res, 'segmento', null);
            $pedido->subsegmento = data_get($res, 'subsegmento', null);
        }

        return response([
            'success' => true,
            'pedidos' => $pedidos,
        ]);
    }

    public function aprobarpedido(Request $request)
    {
        try {

            $gpuser = $request->user();
            $data = $request->all();
            $pedido = DB::transaction(function () use ($data, $gpuser) {
                return $this->approvalService->approveComercialOrder($data, $gpuser);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} aprobado", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al aprobar el pedido: ' . $e->getMessage(), 500);
        }
    }


    public function rechazarpedidoporstock(Request $request)
    {
        try {

            $gpuser = $request->user();
            $data   = $request->all();
            $response = DB::transaction(function () use ($data, $gpuser) {
                return $this->approvalService->denyApproveComercialOrderForInsufficientStock($data, $gpuser);
            });
            $pedido = $response['pedido'];
            $alert = $response['alert'];
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} rechazado por: {$alert}", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar el pedido: ' . $e->getMessage(), 500);
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

    public function consultaparaaprobarpedido(Request $request): JsonResponse
    {
        $data = $request->all();
        return $this->approvalService->getOrderApprovalData($data);
    }



    public function aprobarpedidocrediticio(Request $request)
    {
        try {

            $gpuser = $request->user();
            $pedido = DB::transaction(function () use ($request, $gpuser) {
                $data = $request->all();
                return $this->approvalService->approveCreditOrder($data, $gpuser);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} aprobado", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error en la aprobación crediticia del pedido: ' . $e->getMessage(), 500);
        }
    }

    public function rechazarpedidocrediticio(Request $request)
    {
        try {

            $gpuser = $request->user();
            $pedido = DB::transaction(function () use ($request, $gpuser) {
                $data = $request->all();
                return $this->approvalService->denyCreditApproval($data, $gpuser);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} rechazado", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar el pedido: ' . $e->getMessage(), 500);
        }
    }

    public function rechazarpedidocrediticioporstock(Request $request)
    {
        try {
            $gpuser = $request->user();
            $response = DB::transaction(function () use ($request, $gpuser) {
                $data = $request->all();
                return $this->approvalService->rejectCreditOrderForInsufficientStock($data, $gpuser);
            });
            $pedido = $response['pedido'];
            $alert = $response['alert'];
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} rechazado por: {$alert}", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar el pedido por Stock: ' . $e->getMessage(), 500);
        }
    }



    public function aprobacionComercialCotizacion(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $user = $request->user();
            $quote = DB::transaction(fn() => $this->approvalService->approveComercialQuote($data['id'], $user));
            return $this->buildSuccessResponse("Cotización {$quote->cotizacion} aprobada", 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al aprobar la cotización: ' . $e->getMessage(), 500);
        }
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

    public function rechazarpedido(Request $request)
    {
        try {
            $user = $request->user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
            $data = $request->all();
            $pedido = DB::transaction(function () use ($data, $gpuser) {
                return $this->approvalService->denyApproveComercialOrder($data, $gpuser);
            });
            return $this->buildSuccessResponse("Pedido {$pedido->pedido} rechazado", 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al obtener la información: ' . $e->getMessage(), 500);
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

        return response([
            'success' => true,
            'devoluciones' => $devoluciones,
        ]);
    }


    /** proceso nuevo de rechazar cotizacion cuando esta en espera */
    public function recharCotizacionEstadoEspera(Request $request)
    {
        $id = data_get($request->all(), 'id');
        try {
            $quote = $this->approvalService->denyQuotationBeenWaiting($id, $request->user());
            return $this->successResponse('Cotización rechazada', $quote, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar la cotización: ' . $e->getMessage(), 500);
        }
    }

    public function rechazarPedidoEstadoEspera(Request $request)
    {
        $id = data_get($request->all(), 'id');
        try {
            $order = $this->approvalService->denyOrderBeenWaiting($id, $request->user());
            return $this->successResponse('Pedido rechazado', $order, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar el pedido: ' . $e->getMessage(), 500);
        }
    }
}
