<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\traslados\GpTrasladosVentas;

interface SalesTransferParamsBuilderInterface
{
    public function build(GpPedido $pedido, GpPedidoDetalle $detalle): GpTrasladosVentas;
}
