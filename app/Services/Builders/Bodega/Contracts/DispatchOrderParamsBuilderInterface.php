<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;

interface DispatchOrderParamsBuilderInterface
{
   public function build(GpPedido $pedido, string $estado, ?string $fechaLiberacion = null): GpOrdenDespacho;
}
