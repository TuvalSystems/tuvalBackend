<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpProveedor;
use App\Models\ordendecompra\GpOrdenDeCompra;

interface PurchaseOrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio
     public function formBuilderPruchaseOrder(GpPedido $order, GpPedidoDetalle $detalle, GpPedidoDetalleProveedores $prov, GpProveedor $dataProvider, string $line): GpOrdenDeCompra;

     public function formBuilderHeaderOrderSendGp(GpOrdenDeCompra $order, string $descripcion): array;
}
