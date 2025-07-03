<?php

namespace App\Http\Controllers\ventas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use Illuminate\Http\Request;
use App\Models\GpOrdenDespacho;
use App\Models\GpClienteSucursal;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Http\Controllers\WebserviceController;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use Illuminate\Http\JsonResponse;

class AprobacionesController extends Controller
{
    protected $approvalService;
    public function __construct(ApprovalServiceInterface $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function checkAprobacionesCrediticias($data): string
    {

        if ($data['COND_ID'] == 'CONTADO') {
            return 'no';
        }

        $webservices = new WebserviceController();
        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $data['CLI_RUC'],
        );

        $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

        if ($data['total'] > $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0]['CUPO_DISPONIBLE_GP']) {
            return 'Cupo';
        }

        $documentosVencidos = $webservices->cobranzas('ConsultaDocumentosVencidosCliente', $parameters);

        if ($documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'][0]['DV_SALDO_PENDIENTE'] > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $webservices->cobranzas('ConsultaDocumentosSinSoporteCliente', $parameters);

        if ($documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'][0]['DS_SALDO_PENDIENTE'] > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
    }

    public function estadodecuenta(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $detalle = array();

        $webservices = new WebserviceController();
        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Ruc' => $data['CLI_RUC'],
        );

        $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);

        $detalle['cupoDisponible'] = $cupoDisponible['ConsultaCupoDisponibleClienteResult']['CUPO_CLIENTE'][0];

        $documentosVencidos = $webservices->cobranzas('ConsultaDocumentosVencidosCliente', $parameters);
        if ($documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'][0]['DV_SALDO_PENDIENTE'] > 0) {
            $detalle['documentosVencidos'] = $documentosVencidos['ConsultaDocumentosVencidosClienteResult']['DOCUMENTOS_VENCIDOS'];
        }

        $documentosSinSoporte = $webservices->cobranzas('ConsultaDocumentosSinSoporteCliente', $parameters);
        if ($documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'][0]['DS_SALDO_PENDIENTE'] > 0) {
            $detalle['documentosSinSoporte'] = $documentosSinSoporte['ConsultaDocumentosSinSoporteClienteResult']['DOCUMENTOS_SIN_SOPORTE'];
        }

        return response([
            'success' => true,
            'detalle' => $detalle
        ]);
    }

    public function verpedidosycotizacionesporaprobar(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([
            'puntodeventa_id' => 'required',
        ]);

        $pedidos = GpPedido::join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_pedidos.DESC_FORMA_PAGO_CODIGO')
            ->join('gp_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', 'gp_pedidos.PTO_VTA_ID')
            ->select('gp_pedidos.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
            ->where('requiere_aprobacion_comercial', 'yes')
            ->where('aprobado_comercial', 'no')
            ->where('estado', '!=', 'eliminado')

            ->orderBy('created_at', 'ASC')->get();

        //where('PTO_VTA_ID', $data['puntodeventa_id'])

        $cotizaciones = GpCotizacion::join('gp_descuento_forma_pagos', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
            ->select('gp_cotizacions.*', 'gp_descuento_forma_pagos.DESC_FORMA_PAGO_TIPO_CREDITO')
            ->where('estado', '!=', 'caducado')
            ->where('estado', '!=', 'procesado')
            ->where('requiere_aprobacion_comercial', 'yes')
            ->where('aprobado_comercial', 'no')
            ->orderBy('created_at', 'ASC')->get();

        //where('PTO_VTA_ID', $data['puntodeventa_id'])

        return response([
            'success' => true,
            'pedidos' => $pedidos,
            'cotizaciones' => $cotizaciones,
        ]);
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
            ->where('estado', '!=', 'eliminado')
            ->orderBy('created_at', 'DESC')->get();


        return response([
            'success' => true,
            'pedidos' => $pedidos,
        ]);
    }

    public function aprobarpedido(Request $request)
    {
        $data = $request->all();
        return $this->approvalService->approveComercialOrder($data);
    }


    public function rechazarpedidoporstock(Request $request)
    {
        $data = $request->all();
        return $this->approvalService->denyApproveComercialOrderForInsufficientStock($data);
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
        $data = $request->all();
        return $this->approvalService->approveCreditOrder($data);
    }

    public function rechazarpedidocrediticio(Request $request)
    {

        $data = $request->all();
        return $this->approvalService->denyCreditApproval($data);

    }

    public function rechazarpedidocrediticioporstock(Request $request)
    {
        $data = $request->all();
        return $this->approvalService->rejectCreditOrderForInsufficientStock($data);
    }

    public function aprobarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = Auth::user();

        if (GpCotizacion::where('id', $data['id'])->update(['aprobado_comercial' => 'yes', 'aprobador_comercial_id' => $user->id, 'estado' => 'aprobado'])) {
            return response([
                'success' => true,
                'message' => 'Cotización ' . $data['cotizacion'] . ' aprobada'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
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
        $data = $request->all();
        return $this->approvalService->denyApproveComercialOrder($data);
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
}
