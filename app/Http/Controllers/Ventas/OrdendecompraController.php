<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\WebserviceController;
use App\Http\Controllers\Controller;

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



    public function getViewPurchaseOrder(){

        $orders =  $this->purchaseOrder->getViewPurchaseOrder();
        return $this->successResponse('Información obtenida de la vista',$orders, 200);
    }

    public function getOrdesDispatchPending(){
        $orders =  $this->purchaseOrder->getOrdersDispatchPending();
        return $this->successResponse('Información obtenida de la vista',$orders, 200);
    }
}
