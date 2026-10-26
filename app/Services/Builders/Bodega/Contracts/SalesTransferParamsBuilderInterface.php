<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Inventario\Transferencias\GpTrasladosVentas;

interface SalesTransferParamsBuilderInterface
{
    public function build(GpPedido $pedido, GpPedidoDetalle $detalle): GpTrasladosVentas;
}
