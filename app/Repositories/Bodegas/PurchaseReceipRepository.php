<?php
namespace App\Repositories\Bodegas;

use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;

class PurchaseReceipRepository implements PurchaseReceipRepositoryInterface
{

    public function getGeneratedOrderDetails (int $purchaseOrderId) {
        return  GpOrdenDeCompraGeneradoDetalle::
            // join('gp_pedido_detalle_proveedores','gp_pedido_detalle_proveedores.id','gp_orden_de_compra_generado_detalles.detalle_proveedores_id')
            // ->join('gp_pedido_detalles', 'gp_pedido_detalles.id', 'gp_pedido_detalle_proveedores.detalle_id')
            where('orden_generada_id', $purchaseOrderId)
           // ->select('gp_orden_de_compra_generado_detalles.*', 'gp_pedido_detalles.ART_PESO')
            ->get();
    }
}
