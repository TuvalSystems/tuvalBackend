<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Pedidos\GpPedidoDetalleProveedores;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompra;
use App\Models\Compras\Proveedores\GpProveedor;


interface PurchaseOrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio
     public function formBuilderPruchaseOrder(GpPedido $order, GpPedidoDetalle $detalle, GpPedidoDetalleProveedores $prov, GpProveedor $dataProvider, string $line): GpOrdenDeCompra;

     public function formBuilderHeaderOrderSendGp(GpOrdenDeCompra $order, string $descripcion): array;
}
