<?php

namespace App\Services\Inventario\Transferencias\Builders\Contracts;

use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Inventario\Transferencias\GpTrasladosVentas;

interface SalesTransferParamsBuilderInterface
{
    public function build(GpPedido $pedido, GpPedidoDetalle $detalle): GpTrasladosVentas;
}
