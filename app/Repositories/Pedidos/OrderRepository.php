<?php

namespace App\Repositories\Pedidos;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Models\traslados\GpTrasladosVentas;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{

    public function getAllDetailsOrderById($id)
    {
        return GpPedidoDetalle::where('pedido_id', $id)->get();
    }

    public function getSuppliersByOrderAndItem($pedidoId, $itemId)
    {
        return GpPedidoDetalleProveedores::where('pedido_id', $pedidoId)
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

    public function getPurchaseOrdersByOrderNumber(string $orderNumber)
    {
        return  GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->join('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')
            ->where('gp_pedidos.pedido', $orderNumber)
            ->select(
                'gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN',
                'gp_proveedors.PROV_RAZON_SOCIAL',
                'gp_proveedors.PROV_DIRECCION_1',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE'
            )
            ->get();
    }

    public function getTransferOrdersBySale(int $orderId)
    {

        return GpTrasladosVentas::join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_traslados_ventas.pedido_id')
            ->join('gp_bodegas', 'gp_traslados_ventas.bodega_destino', '=', 'gp_bodegas.BOD_BODEGA')
            ->where('gp_traslados_ventas.pedido_id', $orderId)
            ->select(
                'gp_traslados_ventas.*',
                'gp_pedidos.id AS pedido_id',
                'gp_pedidos.pedido',
                'gp_bodegas.BOD_DESCRIPCION',
                'gp_bodegas.BOD_BODEGA',
                'gp_pedidos.created_at AS fecha',
                'gp_pedidos.USERID AS usuario',
            )
            ->get();
    }

    public function getShipmentGuidesByTransfer(object $transfer)
    {

        return GpGuiaRemisionTransferencia::join('gp_guia_remisions', 'gp_guia_remisions.id', '=', 'gp_guia_remision_transferencias.guia_remision_id')
            ->join('gp_guia_remision_detalles', 'gp_guia_remisions.id', '=', 'gp_guia_remision_detalles.guia_remision_id')
            ->join('gp_traslados_ventas', 'gp_traslados_ventas.id', '=', 'gp_guia_remision_detalles.detalle_id')
            ->where('gp_guia_remision_transferencias.pedido', $transfer->pedido)
            ->where('gp_guia_remision_detalles.GUI_REM_CODI_INTERNO', $transfer->ART_CODIGO)
            ->where('gp_guia_remision_detalles.detalle_id', $transfer->id)
            ->where('gp_guia_remisions.estado', '!=', 'eliminada')
            ->where('gp_traslados_ventas.pedido_id', $transfer->pedido_id)
            ->select(
                'gp_guia_remisions.*',
                'gp_guia_remision_detalles.GUI_REM_CANTIDAD',
                'gp_guia_remision_detalles.recibido'
            )
            ->get();
    }


    public function getDispatchOrder(int $orderId)
    {
        return GpOrdenDespacho::where('pedido_id', $orderId)->first();
    }

    public function getShipmentGuides(string $orderCode)
    {
        return GpGuiaRemision::where('pedido', $orderCode)->where('estado', '!=', 'eliminada')->get();
    }

    public function getReturnsByOrder(int $orderId)
    {
        return GpDevoluciones::where('pedido_id', $orderId)
            ->orderBy('gp_devoluciones.created_at', 'desc')
            ->get();
    }


    /** facturacion de siguiente mes*/

    public function getOrdersToInvoiceNextMonth($mes)
    {
        return GpPedido::where('estado', 'espera')
            ->where('factura_posterior_despacho', 1)
            ->where(function ($q) {
                $q->whereNull('factura_a_demanda')
                    ->orWhere('factura_a_demanda', 0);
            })
            ->where('factura_siguiente_mes', 1)
            ->whereMonth('created_at', '=', $mes)
            ->get();
    }



    public function getStockOrderDetailForCheckExistence($warehouseId, $detailOrder){
      return  GpPedidoDetalleStock::query()
            ->where('STO_ART_BODEGA', $warehouseId)
            ->whereIn('detalle_id', $detailOrder)
            ->where('seleccionado', '>', 0)
            ->get()
            ->keyBy('detalle_id');
    }
}
