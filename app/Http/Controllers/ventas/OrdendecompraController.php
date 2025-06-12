<?php

namespace App\Http\Controllers\ventas;

use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpProveedor;
use App\Models\GpPedido;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Traits\JsonResponseTrait;

class OrdendecompraController extends Controller
{
    use JsonResponseTrait;
    protected $purchaseOrder ;

    public function __construct(PurchaseOrderServiceInterface $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder;
    }
    public function checkordendecomprarecibidos(GpPedido $pedido): bool
    {
        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)
            ->where('ART_TIPO_COMPRA', 'L')
            ->get();

        $flag = false;

        foreach($detalles as $detalle) {
            $proveedores = GpPedidoDetalleProveedores::where('gp_pedido_detalle_proveedores.detalle_id', $detalle->id)
                ->where('recibido', 'no')
                ->count();

            if($proveedores){
                $flag = true;
            }
        }

        if($flag){
            $pedido->estado = 'orden_de_compra';
            $pedido->save();
        }
        return $flag;
    }

    public function checkordendecompra(GpPedido $pedido){

        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)
                ->where('ART_TIPO_COMPRA', 'L')
                ->get();

        $flag = false;

        if(count($detalles)>0){

            $num = 0;
            foreach($detalles as $detalle){
                $num++;
                $proveedores = GpPedidoDetalleProveedores::where('gp_pedido_detalle_proveedores.detalle_id',$detalle->id)
                    ->leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.detalle_proveedores_id', 'gp_pedido_detalle_proveedores.id')
                    ->where('recibido', 'no')
                    ->whereNotExists(function ( $query){
                        $query->select('gp_orden_de_compras.id')->from('gp_orden_de_compras')->
                        whereColumn('gp_orden_de_compras.detalle_proveedores_id', '=', 'gp_pedido_detalle_proveedores.id');
                    })
                    ->select('gp_pedido_detalle_proveedores.*')
                    ->get();
                $i = 0;

                foreach($proveedores as $proveedor){
                    $flag = true;
                    $proovedor_data = GpProveedor::where('id',$proveedor->ID_PROVEEDOR)->first();
                    $i++;
                    $proveedor_save = new GpOrdenDeCompra();
                    $proveedor_save->detalle_id = $detalle->id;
                    $proveedor_save->detalle_proveedores_id = $proveedor->id;
                    $proveedor_save->estado = 'iniciado';
                    $proveedor_save->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                    $proveedor_save->ORDEN_COMPRA_LINEA = $num.'-'.str_pad($i, 2, "0", STR_PAD_LEFT);
                    $proveedor_save->ORDEN_COMPRA_FECHA_DOCUMENTO = $pedido->created_at;
                    $proveedor_save->ORDEN_COMPRA_FECHA_ENTREGA = $proveedor->FECHA_ENTREGA.' 12:00:00';
                    $proveedor_save->ORDEN_COMPRA_ID_CLIENTE = $pedido->CLI_RUC;
                    $proveedor_save->ORDEN_COMPRA_ID_PROVEEDOR = $proovedor_data->PROV_RUC;
                    $proveedor_save->ORDEN_COMPRA_ID_DIRECCION = $proovedor_data->PROV_ID_DIRECCION;
                    $proveedor_save->ORDEN_COMPRA_NOTA_DOCU = $proveedor->NOTA;
                    $proveedor_save->ORDEN_COMPRA_BODEGA = $pedido->BOD_BODEGA;
                    $proveedor_save->ORDEN_COMPRA_ARTICULO = $detalle->ART_CODIGO;
                    $proveedor_save->ORDEN_COMPRA_DESCRIPCION_ARTICULO = $proveedor->DESCRIPCION_ARTICULO;
                    $proveedor_save->ORDEN_COMPRA_UMEDIDA = $detalle->ART_PLAN_UMEDIDA;
                    $proveedor_save->ORDEN_COMPRA_CANTIDAD = $proveedor->CANTIDAD;
                    $proveedor_save->ORDEN_COMPRA_COSTO_UNITARIO = $proveedor->COSTO_UNITARIO;
                    $proveedor_save->ORDEN_COMPRA_TOTAL = $proveedor->TOTAL;
                    $proveedor_save->ORDEN_COMPRA_IMPUESTO = $proveedor->IMPUESTO;
                    $proveedor_save->ORDEN_COMPRA_ID_USUARIO = $pedido->USERID;

                    $proveedor_save->save();
                }
            }
            if($flag) {
                $pedido->estado = 'orden_de_compra';
                $pedido->save();
            }
        }
    }

    public function enviarordenesdecompra(): void
    {

        $webservices = new WebserviceController();
        $ordenes_de_compra = GpOrdenDeCompra::where('estado','iniciado')->get();

        $ordenes_arr = array();
        foreach($ordenes_de_compra as $orden){
            $ordenes_arr[$orden->ORDEN_COMPRA_ID_PROVEEDOR][] = $orden;
        }
        foreach($ordenes_arr as $ordenproveedor){
            $cabecera = array();
            foreach($ordenproveedor as $orden){

//                if(!isset($orden->ORDEN_COMPRA_NOTA_DOCU) || $orden->ORDEN_COMPRA_NOTA_DOCU == '')
//                    $orden->ORDEN_COMPRA_NOTA_DOCU = 'Nota del documento';

                $cabecera[] =  array(
                    'ORDEN_COMPRA_DOCU_ORIGEN' => $orden->ORDEN_COMPRA_DOCU_ORIGEN,
                    'ORDEN_COMPRA_LINEA' => $orden->ORDEN_COMPRA_LINEA,
                    'ORDEN_COMPRA_FECHA_DOCUMENTO' => $orden->ORDEN_COMPRA_FECHA_DOCUMENTO,
                    'ORDEN_COMPRA_FECHA_ENTREGA' => $orden->ORDEN_COMPRA_FECHA_ENTREGA,
                    'ORDEN_COMPRA_ID_CLIENTE' => $orden->ORDEN_COMPRA_ID_CLIENTE,
                    'ORDEN_COMPRA_ID_PROVEEDOR' => $orden->ORDEN_COMPRA_ID_PROVEEDOR,
                    'ORDEN_COMPRA_ID_DIRECCION' => $orden->ORDEN_COMPRA_ID_DIRECCION,
                    'ORDEN_COMPRA_NOTA_DOCU' => $orden->ORDEN_COMPRA_NOTA_DOCU,
                    'ORDEN_COMPRA_BODEGA' => $orden->ORDEN_COMPRA_BODEGA,
                    'ORDEN_COMPRA_ARTICULO' => $orden->ORDEN_COMPRA_ARTICULO,
                    'ORDEN_COMPRA_DESCRIPCION_ARTICULO' => substr(str_replace('*', '',str_replace("`", "",str_replace('  ', ' ', str_replace(';', '-', $orden->ORDEN_COMPRA_DESCRIPCION_ARTICULO)))),0,99),
                    'ORDEN_COMPRA_UMEDIDA' => $orden->ORDEN_COMPRA_UMEDIDA,
                    'ORDEN_COMPRA_CANTIDAD' => $orden->ORDEN_COMPRA_CANTIDAD,
                    'ORDEN_COMPRA_COSTO_UNITARIO' => $orden->ORDEN_COMPRA_COSTO_UNITARIO,
                    'ORDEN_COMPRA_TOTAL' => $orden->ORDEN_COMPRA_TOTAL,
                    'ORDEN_COMPRA_IMPUESTO' => $orden->ORDEN_COMPRA_IMPUESTO,
                    'ORDEN_COMPRA_ID_USUARIO' => $orden->ORDEN_COMPRA_ID_USUARIO,
                );
            }
            $parameters = array(
                'Cabecera' => $cabecera,
            );
//            echo '<pre>';
//            print_r($parameters);
//            die();
            $webservices->transaccional('MST_Orden_Compra', $parameters);

            $parameters = array(
                'documento_origen' => $orden->ORDEN_COMPRA_DOCU_ORIGEN,
                'linea' => $orden->ORDEN_COMPRA_LINEA,
            );

            $result = $webservices->transaccional('MST_Respuesta_Orden_Compra', $parameters);
//            echo '<pre>';
//            print_r($res);
//            die();

            if(isset($result['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0]['DOC_GENERADO']) && $result['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0]['DOC_GENERADO'] != ''){
                foreach($ordenproveedor as $orden){
                    $orden->DOC_GENERADO = $result['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0]['DOC_GENERADO'];
                    $orden->estado = 'enviado';
                    $orden->save();
                }
            }else{
                foreach($ordenproveedor as $orden) {
                    $orden->ORDEN_COMPRA_MENSAJE = $result['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0]['ORDEN_COMPRA_CODIGO'].' - '.$result['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0]['ORDEN_COMPRA_MENSAJE'];
                    $orden->save();
                }
            }
        }

        foreach($ordenes_arr as $orden){
            $this->generarordendecompra($orden);
        }

    }

    public function generarordendecompra($orden): void
    {
        $webservices = new WebserviceController();
        $parameters = array(
            'documento_origen' => $orden[0]->DOC_GENERADO,
        );
        $result = $webservices->transaccional('MST_Generacion_Orden_Compra', $parameters);
//        echo '<pre>';
//        print_r($result);
//        echo '</pre>';
//        exit();
        if(isset($result['MST_Generacion_Orden_CompraResult']['CS_GENERACION_ORDEN_COMPRA'][0])){
            $generado = $result['MST_Generacion_Orden_CompraResult']['CS_GENERACION_ORDEN_COMPRA'][0];

            $orden_save = new GpOrdenDeCompraGenerado;
            $orden_save->orden_id = $orden[0]->id;
            $orden_save->detalle_id = $orden[0]->detalle_id;
            $orden_save->detalle_proveedores_id = $orden[0]->detalle_proveedores_id;
            $orden_save->estado = 'generado';
            $orden_save->ORDEN_COMPRA_DOCUMENTO = $generado['ORDEN_COMPRA_DOCUMENTO'];
            $orden_save->ORDEN_COMPRA_FECHA_DOCUMENTO = $generado['ORDEN_COMPRA_FECHA_DOCUMENTO']['date'];
            $orden_save->ORDEN_COMPRA_FECHA_ENTREGA = $generado['ORDEN_COMPRA_FECHA_ENTREGA']['date'];
            $orden_save->ORDEN_COMPRA_ID_PROVEEDOR = $generado['ORDEN_COMPRA_ID_PROVEEDOR'];
            $orden_save->ORDEN_COMPRA_DIRECCION = $generado['ORDEN_COMPRA_DIRECCION'];
            $orden_save->ORDEN_COMPRA_NOTA_DOCU = $generado['ORDEN_COMPRA_NOTA_DOCU'];
            $orden_save->recibidos = 1;
            $orden_save->save();

            foreach($generado['ORDEN_COMPRA_DETALLE']['CS_GENERACION_ORDEN_COMPRA_DETALLE'] as $keyGen => $det){
                $det_save = new GpOrdenDeCompraGeneradoDetalle;
                $det_save->orden_generada_id = $orden_save->id;

                $detalle_proveedores_id = null;
                foreach($orden as $item){
                    if($item->ORDEN_COMPRA_ARTICULO == trim($det['ORDEN_COMPRA_DETALLE_ARTICULO'])){
                        $detalle_proveedores_id = $item->detalle_proveedores_id;
                        $item->estado = 'generado';
                        $item->save();
                    }
                }
                $det_save->detalle_proveedores_id = $detalle_proveedores_id;
                $det_save->ORDEN_COMPRA_DETALLE_BODEGA = $det['ORDEN_COMPRA_DETALLE_BODEGA'];
                $det_save->ORDEN_COMPRA_DETALLE_ARTICULO = $det['ORDEN_COMPRA_DETALLE_ARTICULO'];
                $det_save->ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO = $det['ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO'];
                $det_save->ORDEN_COMPRA_DETALLE_UMEDIDA = $det['ORDEN_COMPRA_DETALLE_UMEDIDA'];
                $det_save->ORDEN_COMPRA_DETALLE_CANTIDAD = $det['ORDEN_COMPRA_DETALLE_CANTIDAD'];
                $det_save->ORDEN_COMPRA_DETALLE_COSTO_UNITARIO = $det['ORDEN_COMPRA_DETALLE_COSTO_UNITARIO'];
                $det_save->ORDEN_COMPRA_DETALLE_TOTAL = $det['ORDEN_COMPRA_DETALLE_TOTAL'];
                $det_save->ORDEN_COMPRA_DETALLE_IMPUESTO = $det['ORDEN_COMPRA_DETALLE_IMPUESTO'];
                $det_save->save();
            }
        }
    }

    public function enviarordenesdecompraurl(){

        $ordenes_de_compra = GpOrdenDeCompra::where('estado','enviado')
            ->where('DOC_GENERADO', 'OCL0001001')
            ->get();

        $ordenes_arr = array();
        foreach($ordenes_de_compra as $orden){
            $ordenes_arr[$orden->ORDEN_COMPRA_ID_PROVEEDOR][] = $orden;
        }

        foreach($ordenes_arr as $orden){
            $this->generarordendecompra($orden);
        }
    }


    public function getViewPurchaseOrder(){

        $orders =  $this->purchaseOrder->getViewPurchaseOrder();
        return $this->successResponse('Información obtenida de la vista',$orders, 200);
    }

    public function getOrdesDispatchPending(){
        $orders =  $this->purchaseOrder->getOrdersDispatchPending();
        return $this->successResponse('Información obtenida de la vista',$orders, 200);
    }
}
