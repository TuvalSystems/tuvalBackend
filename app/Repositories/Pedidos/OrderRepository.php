<?php

namespace App\Repositories\Pedidos;

use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{

    public function getAllDetailsOrderById($id)
    {
        return GpPedidoDetalle::where('pedido_id', $id)->get();
    }

    public function getSuppliersByOrderAndItem($pedidoId, $itemId)
    {
        return  GpPedidoDetalleProveedores::where('pedido_id', $pedidoId)
            ->join('gp_proveedors', 'gp_proveedors.id', 'gp_pedido_detalle_proveedores.ID_PROVEEDOR')
            ->where('detalle_id', $itemId)
            ->select('gp_pedido_detalle_proveedores.*', 'gp_proveedors.PROV_RAZON_SOCIAL')
            ->get();
    }

    public function getPurchaseOrdersBySupplier($proveedorId)
    {
        return GpOrdenDeCompraGeneradoDetalle::join('gp_orden_de_compra_generados', 'gp_orden_de_compra_generados.id', 'gp_orden_de_compra_generado_detalles.orden_generada_id')
            ->where('gp_orden_de_compra_generado_detalles.detalle_proveedores_id', $proveedorId)
            ->select('gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO', 'gp_orden_de_compra_generados.ORDEN_COMPRA_DOCUMENTO')
            ->get();
    }

    public function getStockByItemAndWarehouse(int $itemId, string $warehouse)
    {
        return GpPedidoDetalleStock::where('detalle_id', $itemId)
            ->where('STO_ART_BODEGA', $warehouse)
            ->first();
    }

    public function getPurchaseOrdersByOrderNumber(string $orderNumber){
        return GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->join('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')
            ->where('gp_pedidos.pedido', $orderNumber)
            ->select(
                'gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN as document_origin',
                'gp_proveedors.PROV_RAZON_SOCIAL as supplier_name',
                'gp_proveedors.PROV_DIRECCION_1 as supplier_address',
                'gp_pedidos.CLI_RAZON_SOCIAL as client_name',
                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE as client_id'
            )
            ->get();
    }
}
