<?php

namespace App\Http\Controllers\ventas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpOrdenDespacho;
use App\Models\GpClienteSucursal;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Http\Controllers\WebserviceController;
use App\Models\GpUsuario;
use App\Services\Api\ApiGpService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AprobacionesController extends Controller
{
    use JsonResponseTrait;
    protected $approvalService;
    protected $apiGpService;

    public function __construct(ApiGpService $apiGpService, ApprovalServiceInterface $approvalService)
    {
        $this->apiGpService = $apiGpService;
        $this->approvalService = $approvalService;
    }

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


    // public function estadodecuenta(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    // {
    //     $data = $request->all();
    //     $user = Auth::user();

    //     $detalle = array();

    //     $webservices = new WebserviceController();
    //     $parameters = array(
    //         'IDCompania' => env('ID_COMPANIA'),
    //         'Ruc' => $data['CLI_RUC'],
    //     );

    //     $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

    //     $detalle['cupoDisponible'] = $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0];

    //     $documentosVencidos = $webservices->cobranzas('ConsultaDocumentosVencidosCliente', $parameters);
    //     if ($documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'][0]['DV_SALDO_PENDIENTE'] > 0) {
    //         $detalle['documentosVencidos'] = $documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'];
    //     }

    //     $documentosSinSoporte = $webservices->cobranzas('ConsultaDocumentosSinSoporteCliente', $parameters);
    //     if ($documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'][0]['DS_SALDO_PENDIENTE'] > 0) {
    //         $detalle['documentosSinSoporte'] = $documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'];
    //     }

    //     return response([
    //         'success' => true,
    //         'detalle' => $detalle
    //     ]);
    // }

    public function verpedidosycotizacionesporaprobar(Request $request): JsonResponse
    {

        $data = $request->validate([
            'puntodeventa_id' => 'required',
        ]);
        try {
            $data = $this->approvalService->getAllPendingApprovals($data['puntodeventa_id']);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la información: ' . $e->getMessage(), null, 500);
        }

        // $pedidos = GpPedido::join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_pedidos.DESC_FORMA_PAGO_CODIGO')
        //     ->join('gp_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', 'gp_pedidos.PTO_VTA_ID')
        //     ->select('gp_pedidos.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
        //     ->where('requiere_aprobacion_comercial', 'yes')
        //     ->where('aprobado_comercial', 'no')
        //     ->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado', 'facturado', 'enviando', 'procesado'])

        //     ->orderBy('created_at', 'ASC')->get();

        // foreach ($pedidos as $key => $pedido) {
        //     $res = $this->getSegmentoCliente($pedido->CLI_RUC);
        //     $pedido->segmento = data_get($res, 'segmento', null);
        //     $pedido->subsegmento = data_get($res, 'subsegmento', null);
        // }
        // //where('PTO_VTA_ID', $data['puntodeventa_id'])

        // $cotizaciones = GpCotizacion::join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
        //     ->select('gp_cotizacions.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO')
        //     ->whereNotIn('estado', ['caducado', 'procesado', 'rechazado', 'aprobado', 'eliminado'])
        //     ->where('requiere_aprobacion_comercial', 'yes')
        //     ->where('aprobado_comercial', 'no')
        //     ->orderBy('created_at', 'ASC')->get();
        // foreach ($cotizaciones as $key => $cot) {

        //     $res2 = $this->getSegmentoCliente($cot->CLI_RUC);
        //     $cot->segmento = data_get($res2, 'segmento', null);
        //     $cot->subsegmento = data_get($res2, 'subsegmento', null);
        // }

        // //where('PTO_VTA_ID', $data['puntodeventa_id'])

        // return response([
        //     'success' => true,
        //     'pedidos' => $pedidos,
        //     'cotizaciones' => $cotizaciones,
        // ]);
    }

    public function listaCotizacionesPendientesPorAprobar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'puntodeventa_id' => 'required',
        ]);
        $ptoVta = data_get($data, 'puntodeventa_id');
        try {
            $data = $this->approvalService->getAllQuotesPendingApprovals($ptoVta);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la información: ' . $e->getMessage(), null, 500);
        }
    }

    public function listaPedidosPendientesPorAprobar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'puntodeventa_id' => 'required',
        ]);
        $ptoVta = data_get($data, 'puntodeventa_id');
        try {
            $data = $this->approvalService->getAllOrdersPendingApprovals($ptoVta);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error al obtener la información: ' . $e->getMessage(), null, 500);
        }
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
            ->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado', 'facturado', 'enviando', 'procesado'])
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
            $user = Auth::user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
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
            $user = Auth::user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
            $data = $request->all();
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
        $user = Auth::user();

        $devolucion = GpDevoluciones::where('id', $data['id'])->first();

        $devolucion->aprobado = 'yes';
        $devolucion->estado = 'aprobado';

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
            ], 401);
        }
    }

    public function rechazardevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = Auth::user();

        $devolucion = GpDevoluciones::where('id', $data['id'])->first();
        $devolucion->aprobado = 'yes';
        $devolucion->estado = 'rechazado';
        $devolucion->aprobador_id = $user->id;
        if ($devolucion->save()) {

            return response([
                'success' => true,
                'message' => 'Devolucion ' . $data['devolucion'] . ' rechazada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
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
            $user = Auth::user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
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
            $user = Auth::user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
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
            $user = Auth::user();
            $gpuser = GpUsuario::where('id', $user->id)->first();
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

    // public function aprobarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    // {

    //     $data = $request->all();
    //     $user = Auth::user();

    //     if (GpCotizacion::where('id', $data['id'])->update(['aprobado_comercial' => 'yes', 'aprobador_comercial_id' => $user->id, 'estado' => 'aprobado'])) {
    //         return response([
    //             'success' => true,
    //             'message' => 'Cotización ' . $data['cotizacion'] . ' aprobada'
    //         ]);
    //     } else {
    //         return response([
    //             'error' => 'Hubo un error al guardar'
    //         ], 401);
    //     }
    // }

    public function aprobacionComercialCotizacion(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $user = Auth::user();
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
        $user = Auth::user();

        if (GpCotizacion::where('id', $data['id'])->update(['aprobado_comercial' => 'yes', 'aprobador_comercial_id' => $user->id, 'estado' => 'rechazado'])) {
            return response([
                'success' => true,
                'message' => 'Cotización ' . $data['cotizacion'] . ' rechazada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }
    }

    public function rechazarpedido(Request $request)
    {
        try {
            $user = Auth::user();
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
            $quote = $this->approvalService->denyQuotationBeenWaiting($id);
            return $this->successResponse('Cotización rechazada', $quote, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar la cotización: ' . $e->getMessage(), 500);
        }
    }

    public function rechazarPedidoEstadoEspera(Request $request)
    {
        $id = data_get($request->all(), 'id');
        try {
            $order = $this->approvalService->denyOrderBeenWaiting($id);
            return $this->successResponse('Pedido rechazado', $order, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse('Error al rechazar el pedido: ' . $e->getMessage(), 500);
        }

    }
}
