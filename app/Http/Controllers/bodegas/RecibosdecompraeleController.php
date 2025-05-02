<?php

namespace App\Http\Controllers\bodegas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use Illuminate\Http\Request;
use App\Models\Notificaciones;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\ventas\PedidosController;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;

class RecibosdecompraeleController extends Controller
{
    public function bandeja(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $request->validate([
            'puntodeventa_id' => '',
        ]);

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $request->puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $ordendecompra = GpOrdenDeCompraGenerado::
            leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->leftJoin('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')

            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA',$bodega)
            ->where('gp_orden_de_compra_generados.estado','generado')
            ->select('gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN',
                'gp_proveedors.PROV_RAZON_SOCIAL',
                'gp_proveedors.PROV_DIRECCION_1',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_pedidos.USERID',
                DB::raw("CONCAT(gp_pedidos.VEND_NOMBRES,' ',gp_pedidos.VEND_APELLIDOS) AS vendedor"),

                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE')
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA','ASC')
            ->get();


        $recibidos = GpOrdenDeCompraGenerado::
            leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA',$bodega)
            ->where('gp_orden_de_compra_generados.estado','recibido')
            ->select('gp_orden_de_compra_generados.*', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN', 'gp_proveedors.PROV_RAZON_SOCIAL', 'gp_proveedors.PROV_DIRECCION_1')
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA','DESC')
            ->get();

        $eliminados = GpOrdenDeCompraGenerado::
        leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->where('gp_orden_de_compras.ORDEN_COMPRA_BODEGA',$bodega)
            ->where('gp_orden_de_compra_generados.estado','eliminado')
            ->select('gp_orden_de_compra_generados.*', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN', 'gp_proveedors.PROV_RAZON_SOCIAL', 'gp_proveedors.PROV_DIRECCION_1')
            ->orderBy('gp_orden_de_compra_generados.ORDEN_COMPRA_FECHA_ENTREGA','DESC')
            ->get();

        return response([
            'success' => true,
            'bandeja' => $ordendecompra,
            'recibidos' => $recibidos,
            'eliminados' => $eliminados,
        ]);
    }

    public function detalle(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $detalle = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $data['id'])->get();

        foreach($detalle as $det){

            if(!$det->recibido)
                $det->recibido = 0;

            $det->ORDEN_COMPRA_DETALLE_CANTIDAD = number_format($det->ORDEN_COMPRA_DETALLE_CANTIDAD, 2, '.','');

            $det->pendiente = number_format($det->ORDEN_COMPRA_DETALLE_CANTIDAD - $det->recibido,2,'.','');
            $det->seleccionado = 0;
        }

        return response([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    public function recibir(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $orden = GpOrdenDeCompraGenerado::where('id',$data['id'])->get()->first();

        $cabecera = array(
            array(
                'REC_COM_DOCU_ORIGEN' => $orden->ORDEN_COMPRA_DOCUMENTO.'_'.$orden->recibidos,
                'REC_COM_ID_TIPO' => 1,
                'REC_COM_ID_PROVEEDOR' => $orden->ORDEN_COMPRA_ID_PROVEEDOR,
                'REC_COM_FECHA' => date('Y-m-d'),
            )
        );

        $DetalleOrden = array();

        foreach($data['detalle'] as $detalle){

            if($detalle['seleccionado'] > 0){
                $DetalleOrden[] = array(
                    'REC_COM_DOCU_ORIGEN' =>  $orden->ORDEN_COMPRA_DOCUMENTO.'_'.$orden->recibidos,
                    'REC_COM_ID_TIPO' => 1,
                    'REC_COM_ID_ARTICULO' => $detalle['ORDEN_COMPRA_DETALLE_ARTICULO'],
                    'REC_COM_DESCRIPCION_ARTICULO' => $detalle['ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO'],
                    'REC_COM_UNIDAD_MEDIDA' => $detalle['ORDEN_COMPRA_DETALLE_UMEDIDA'],
                    'REC_COM_BODEGA' => $detalle['ORDEN_COMPRA_DETALLE_BODEGA'],
                    'REC_COM_CANTIDAD' => $detalle['seleccionado'],
                );
            }


        }

        $DetalleOrden = array_values($DetalleOrden);

        $parameters = array(
            'Cabecera' => $cabecera,
            'Detalle' => $DetalleOrden,
        );

        $webservices = new WebserviceController();
        $result = $webservices->transaccional('MST_RECIBO_COMPRA', $parameters);
//        echo '<pre>';
//        print_r($parameters);
//        print_r($result);
        $listo = false;

        if(isset($result['MST_RECIBO_COMPRAResult'])) {

            $parameters = array(
                'documento_origen' => $orden->ORDEN_COMPRA_DOCUMENTO.'_'.$orden->recibidos,
                'tipo_documento' => 1,
            );
            $result = $webservices->transaccional('MST_Respuesta_Recibo_Compra', $parameters);

            if(isset($result['MST_Respuesta_Recibo_CompraResult']['CS_RESPUESTA_RECIBO_COMPRA'][0]['DOC_GENERADO'])
                && $result['MST_Respuesta_Recibo_CompraResult']['CS_RESPUESTA_RECIBO_COMPRA'][0]['DOC_GENERADO'] != ''){
                $orden->ORDEN_COMPRA_RECIBO .= '_'.$result['MST_Respuesta_Recibo_CompraResult']['CS_RESPUESTA_RECIBO_COMPRA'][0]['DOC_GENERADO'];
                $orden->recibidos += 1;
                $orden->save();
                $this->checkordenrecibida($orden, $data['detalle']);
                $listo = true;
            }
        }

        if($listo){

            $ingresos = new IngresosController();
            $ingresos->ingreso('compra', $data, $data['detalle']);

            return response([
                'success' => true,
                'message' => 'Orden de compra recibida',
            ]);
        }else{
            return response([
                'error' => 'El recibo de orden de compra aun no ha sido contabilizado<br>Recibir la orden de compra en un minuto'
            ], 401);
        }
    }

    public function imprimirrecibol(Request $request): \Illuminate\Http\Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenReciboLPDF', $data);
        return $pdf->stream('orden_recibo_'.$data['ORDEN_COMPRA_DOCUMENTO'].'.pdf');
    }

    public function eliminar(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $orden = GpOrdenDeCompraGenerado::where('id',$data['id'])->get()->first();
        $orden->estado = 'eliminado';
        $orden->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra eliminada',
        ]);
    }

    public function checkordenrecibida($orden, $detalle){

        $user = Auth::user();

        $recibida = true;

        foreach($detalle as $det){
            $det_proveedor = GpPedidoDetalleProveedores::where('id',$det['detalle_proveedores_id'])->get()->first();

            if(!$det_proveedor->ingresado)
                $det_proveedor->ingresado = 0;

            $det_proveedor->ingresado += $det['seleccionado'];

            if($det_proveedor->CANTIDAD == $det_proveedor->ingresado){
                $det_proveedor->recibido = 'yes';
            }else{
                $recibida = false;
            }
            $det_proveedor->save();

            $orden_detalle = GpOrdenDeCompraGeneradoDetalle::where('id',$det['id'])->get()->first();

            if(!$orden_detalle->recibido)
                $orden_detalle->recibido = 0;

            $orden_detalle->recibido += $det['seleccionado'];
            $orden_detalle->save();
        }

        $orden_compra = GpOrdenDeCompra::where('id', $orden->orden_id)->first();
        $pedido = GpPedido::where('pedido', $orden_compra->ORDEN_COMPRA_DOCU_ORIGEN)->first();

        if($recibida){
            $orden->estado = 'recibido';
            $orden->USERID = $user->USERID;
            $orden->save();
            $order = app(OrderServiceInterface::class);
            $apigp = app(ApiGpService::class);
            $pedidosControl = new PedidosController(    $apigp, $order);

            $cliente = GpCliente::where('id', $pedido->cliente_id)->first();
            if($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI'){
                $pedido->factura_posterior_despacho = 1;
                if($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1){
                    $pedido->factura_a_demanda = 1;
                }elseif(date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION){
                    $pedido->factura_siguiente_mes = 1;
                }
                $pedido->save();
            }

            $pedidosControl->checkenviarfactura($pedido, true);
        }

        $notificacion = new Notificaciones();
        $notificacion->titulo = 'Artículo/s L recibidos';
        $notificacion->user_id = $pedido->user_id;
        $notificacion->mensaje = 'Artículo/s L del pedido '.$pedido->pedido.' han sido recibidos en la bodega '.$pedido->BOD_BODEGA;
        $notificacion->estado = 'nuevo';
        $notificacion->save();
    }

    public function setdetalleordendecompra($ordendecompra){
        $detalleOrdendecompra = GpOrdenDeCompraGeneradoDetalle::join('gp_pedido_detalle_proveedores','gp_pedido_detalle_proveedores.id','gp_orden_de_compra_generado_detalles.detalle_proveedores_id')
            ->join('gp_pedido_detalles', 'gp_pedido_detalles.id', 'gp_pedido_detalle_proveedores.detalle_id')
            ->where('orden_generada_id', $ordendecompra->id)
            ->select('gp_orden_de_compra_generado_detalles.*', 'gp_pedido_detalles.ART_PESO')
            ->get();
        $ordendecompra->subtotal = 0;
        $ordendecompra->iva = 0;
        foreach($detalleOrdendecompra as $det){
            $ordendecompra->subtotal += $det->ORDEN_COMPRA_DETALLE_TOTAL;
            $ordendecompra->iva += $det->ORDEN_COMPRA_DETALLE_IMPUESTO;
        }

        $ordendecompra->subtotal = number_format($ordendecompra->subtotal,'2','.','');
        $ordendecompra->iva = number_format($ordendecompra->iva,'2','.','');
        $ordendecompra->total = number_format($ordendecompra->subtotal + $ordendecompra->iva,'2','.','');

        $ordendecompra->detalle = $detalleOrdendecompra;
        return $ordendecompra;
    }

    public function imprimir($id): \Illuminate\Http\Response
    {
        $ordendecompra = GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->join('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')
            ->where('gp_orden_de_compra_generados.id',$id)
            ->select('gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN',
                'gp_orden_de_compras.ORDEN_COMPRA_BODEGA',
                'gp_orden_de_compras.ORDEN_COMPRA_NOTA_DOCU',
                'gp_proveedors.PROV_RAZON_SOCIAL',
                'gp_proveedors.PROV_DIRECCION_1',
                'gp_proveedors.PROV_TELEFONO_1',
                'gp_proveedors.PROV_CONDICION_PAGO',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_pedidos.USERID',
                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE')
            ->first();
        if($ordendecompra){
            $this->setdetalleordendecompra($ordendecompra);
        }

//        echo '<pre>';
//        print_r($ordendecompra);
//        echo '</pre>';
//        die();

        //return view('ordendecompraPDF', $ordendecompra);
        $pdf = PDF::loadView('ordendecompraPDF', $ordendecompra->toArray());
        return $pdf->stream('ordendecompra-'.$ordendecompra->ORDEN_COMPRA_DOCUMENTO.'.pdf');
    }
}
