<?php

namespace App\Http\Controllers\ventas;

use DateTime;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use App\Models\GpDatosItemsL;
use App\Models\Notificaciones;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use App\Models\GpClienteSucursal;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Http\Controllers\Controller;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Support\Facades\Auth;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\FuncionesController;
use App\Http\Controllers\WebserviceController;
use App\Models\GpCotizacionDetalleProveedores;
use App\Http\Controllers\ventas\PedidosController;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use Illuminate\Http\JsonResponse;

class AprobacionesController extends Controller
{
    protected $approvalService;
    public function __construct(ApprovalServiceInterface $approvalService){
        $this->approvalService = $approvalService;
    }
    public function checkAprobacionesComerciales($data, $pedido = false): array
    {
        $return = array('requiere' => 'no', 'motivo' => '');
        $nivel_forma_de_pago_cliente = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $data['COND_ID_ORIGINAL'])
            ->orderBy('DESC_FORMA_PAGO_CODIGO')->first()->nivel;

        $nivel_forma_de_pago_cotizacion = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $data['DESC_FORMA_PAGO_CODIGO'])
            ->orderBy('DESC_FORMA_PAGO_CODIGO')->first()->nivel;

        $porcentajeMargen = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', 'VTA-BNS-L')->get()->first();

        if (isset($data['cotizacion_id']) && $data['cotizacion_id'] != '') {

            $cotizacion = GpCotizacion::where('id', $data['cotizacion_id'])->get()->first();

            $nivel_forma_de_pago_cotizacion_anterior = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion->DESC_FORMA_PAGO_CODIGO)
                ->orderBy('DESC_FORMA_PAGO_CODIGO')->first()->nivel;

            if ($nivel_forma_de_pago_cotizacion > $nivel_forma_de_pago_cotizacion_anterior) {
                if ($nivel_forma_de_pago_cotizacion > $nivel_forma_de_pago_cliente) {
                    $cotizacion->aprobado_comercial = 'no';
                    $cotizacion->save();
                    $return['requiere'] = 'yes';
                    $return['motivo'] = 'Forma de pago de cotización mayor que la del cliente modificando cotización: ' . $nivel_forma_de_pago_cotizacion . ' - ' . $nivel_forma_de_pago_cliente;
                    return $return;
                }
            }

            foreach ($data['cotizacion'] as $detalle) {

                if (isset($detalle['detalle_id']) && $detalle['detalle_id'] != '') {
                    $det_original = GpCotizacionDetalle::where('id', $detalle['detalle_id'])->first();

                    if ($det_original) {
                        $det_original->toArray();
                        if ($pedido) {
                            if (($detalle['descuento'] != $detalle['descuento_original']) && $detalle['descuento'] != $det_original['descuento']) {
                                $return['requiere'] = 'yes';
                                $return['motivo'] = 'Descuento del detalle diferente al descuento original - ' . $detalle['detalle_id'];
                                return $return;
                            }
                        }


                        if ($detalle['ART_TIPO_COMPRA'] == 'L') {

                            foreach ($detalle['proveedores'] as $proveedor) {
                                $proveedor_original = GpCotizacionDetalleProveedores::where('detalle_id', $det_original['id'])->first();

                                if ($proveedor['MARGEN'] < $proveedor_original['MARGEN']) {
                                    $return['requiere'] = 'yes';
                                    $return['motivo'] = 'El margen del producto L es menor al margen aprobado en la cotización';
                                    return $return;
                                }
                            }
                        } else {
                            if ($pedido) {
                                if ($detalle['ART_VALOR'] < $det_original['ART_VALOR']) {
                                    $return['requiere'] = 'yes';
                                    $return['motivo'] = 'Valor menor que el detalle original - ' . $detalle['detalle_id'] . ': ' . $detalle['ART_VALOR'] . ' - ' . $det_original['ART_VALOR'];
                                    return $return;
                                }
                            }

                            if ($detalle['ART_DESCRIPCION'] != $det_original['ART_DESCRIPCION']) {
                                $return['requiere'] = 'yes';
                                $return['motivo'] = 'Descripción diferente a la descripción original - ' . $detalle['detalle_id'];
                                return $return;
                            }

                            if (($detalle['descuento'] != $detalle['descuento_original']) && $detalle['descuento'] > $det_original['descuento']) {
                                $return['requiere'] = 'yes';
                                $return['motivo'] = 'Descuento mayor que el detalle original - ' . $detalle['detalle_id'];
                                return $return;
                            }

                        }
                    }
                }
            }

            if (
                $cotizacion->requiere_aprobacion_comercial == 'yes' && $cotizacion->aprobado_comercial == 'yes'
                && (($data['total'] < ($cotizacion->total + 0.10)) || ($data['total'] > ($cotizacion->total - 0.10)))
            ) {
                $return['requiere'] = 'no';
                $return['motivo'] = 'Total correcto - ';
                return $return;
            }
        }

        if (!$pedido) {
            $registro_copia = GpRegistroCopia::where('id', $data['registro_copia'])
                ->where('tipo', 'volver_a_pedir')->first();
            if ($registro_copia) {
                $desde = 'cotización';
                $origen = 'COT' . str_pad($registro_copia->cotizacion_origen, 8, "0", STR_PAD_LEFT);
                if ($registro_copia->pedido_origen) {
                    $desde = 'pedido';
                    $origen = 'PED' . str_pad($registro_copia->pedido_origen, 8, "0", STR_PAD_LEFT);
                    $fecha_origen = GpPedido::where('id', $registro_copia->pedido_origen)->first();
                } else {
                    $fecha_origen = GpCotizacion::where('id', $registro_copia->cotizacion_origen)->first();
                }
                $return['requiere'] = 'yes';
                $return['motivo'] = 'Cotización desde VOLVER A PEDIR desde ' . $desde . ' original: ' . $origen . ', fecha: ' . substr($fecha_origen->created_at, 0, 10);
                return $return;
            }
        }

        if ($nivel_forma_de_pago_cotizacion > $nivel_forma_de_pago_cliente) {
            $return['requiere'] = 'yes';
            $return['motivo'] = 'Nivel de forma de pago de la cotización es mayor que la del cliente: ' . $nivel_forma_de_pago_cotizacion . ' - ' . $nivel_forma_de_pago_cliente;
            return $return;
        }

        if ($data['porcentage_descuento'] != $data['porcentage_descuentooriginal']) {
            $return['requiere'] = 'yes';
            $return['motivo'] = 'Porcentaje de descuento general diferente a la original';
            return $return;
        }

        $porcentajeMaximoPuntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->get()->first()->toArray();
        foreach ($data['cotizacion'] as $detalle) {

            if ($detalle['descuento'] != $detalle['descuento_original']) {
                $return['requiere'] = 'yes';
                $return['motivo'] = 'Porcentaje de descuento general diferente a la original';
                return $return;
            }


            if ($detalle['ART_TIPO_COMPRA'] == 'L') {

                foreach ($detalle['proveedores'] as $proveedor) {
                    $margen = number_format((($proveedor['COSTO_UNITARIO'] / (1 - ($porcentajeMargen->DATOS_ITEMS_L_MARGEN / 100))) / (1 - $detalle['descuento'] / 100)), 4, '.', '');
                    if ($margen > $detalle['ART_VALOR']) {
                        $return['requiere'] = 'yes';
                        $return['motivo'] = 'El margen del producto L es mayor que el valor del producto';
                        return $return;
                    }
                }

            } else {
                if ($detalle['ART_VALOR'] < $detalle['ART_VALOR_ORIGINAL']) {
                    $return['requiere'] = 'yes';
                    $return['motivo'] = 'El valor del producto es menor que el valor original';
                    return $return;
                }


                if ($detalle['ART_VALOR'] > ($detalle['ART_VALOR_ORIGINAL'] * (1 + ($porcentajeMaximoPuntoVenta['PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO'] / 100)))) {
                    $return['requiere'] = 'yes';
                    $return['motivo'] = 'El valor del producto es mayor que el valor original y supera el PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO';
                    return $return;
                }


                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL']) {
                    $return['requiere'] = 'yes';
                    $return['motivo'] = 'Descripción del producto diferente a la original';
                    return $return;
                }

            }

        }

        $return['requiere'] = 'no';
        $return['motivo'] = null;
        return $return;
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

    public function aprobarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = Auth::user();
        $pedido = GpPedido::where('id', $data['id'])->first();

        $pedido->aprobado_comercial = 'yes';
        $pedido->aprobador_comercial_id = $user->id;
        if ($pedido->save()) {
            $order = app(OrderServiceInterface::class);
            $apiGp = app(ApiGpService::class);
            $aproval = app(ApprovalServiceInterface::class);
            $pedidos = new PedidosController($apiGp, $order, $aproval);
            $pedidos->checkenviarfactura($pedido);

            return response([
                'success' => true,
                'message' => 'Pedido ' . $data['pedido'] . ' aprobado'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }
    }


    public function rechazarpedidoporstock(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = Auth::user();

        $pedido = GpPedido::where('id', $data['id'])->first();

        $pedido->estado = 'rechazado';
        $pedido->aprobado_comercial = 'yes';
        $pedido->aprobador_comercial_id = $user->id;
        $pedido->aprobado_comercial_mensaje = $data['alert'];
        $pedido->fecha_hora_rechazado = date('Y-m-d H:i:s');
        $pedido->rechazado = 1;
        $pedido->save();

        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();

        $notificacion = new Notificaciones();
        $notificacion->titulo = 'Pedido rechazado por falta de stock';
        $notificacion->user_id = $pedido->user_id;
        $notificacion->mensaje = 'Para el pedido ' . $pedido->pedido . ' de la cotización ' . $pedido->cotizacion_id . ': ' . $data['alert'];
        $notificacion->estado = 'nuevo';
        $notificacion->save();

        //$pedido->delete();

        return response([
            'success' => true,
            'message' => $data['alert'],
        ]);
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

    public function consultaparaaprobarpedido(Request $request) : JsonResponse
    {
        $data = $request->all();
        return  $this->approvalService->getOrderApprovalData($data);
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

    public function rechazarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->all();
        $user = Auth::user();

        if (GpPedido::where('id', $data['id'])->update(['aprobado_comercial' => 'yes', 'aprobador_comercial_id' => $user->id, 'estado' => 'rechazado'])) {
            GpTrasladosVentas::where('pedido_id', $data['id'])->delete();
            return response([
                'success' => true,
                'message' => 'Pedido ' . $data['pedido'] . ' rechazado'
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }
    }

    public function checkAprobacionesDevoluciones($data): string
    {

        if ($data['devolucion']['tipo_devolucion'] == 'parcial') {
            if (!$data['devolucion']['volver_a_facturar']) {
                if (!$data['pedido']['despachado']) {
                    $tiene_L = false;
                    foreach ($data['detallepedido'] as $detalle) {
                        if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                            $tiene_L = true;
                        }
                    }
                    if (!$tiene_L) {
                        return 'no';
                    }
                }
            }
        }

        if ($data['devolucion']['tipo_devolucion'] == 'total') {

            if (!$data['devolucion']['volver_a_facturar']) {
                $tiene_L = false;
                foreach ($data['detallepedido'] as $detalle) {
                    if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                        $tiene_L = true;
                    }
                }
                if (!$data['pedido']['despachado']) {
                    if (!$tiene_L) {
                        return 'no';
                    }
                } else {
                    if (!$tiene_L) {
                        $origin = new DateTime($data['pedido']['fecha_factura']);
                        $target = new DateTime('now');
                        if ($origin->diff($target)->days <= 7) {
                            return 'no';
                        }
                    }
                }
            }
        }
        return 'yes';
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
