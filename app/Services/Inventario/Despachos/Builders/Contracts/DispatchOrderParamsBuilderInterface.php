<?php

namespace App\Services\Inventario\Despachos\Builders\Contracts;

use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;

interface DispatchOrderParamsBuilderInterface
{
   public function build(GpPedido $pedido, string $estado, ?string $fechaLiberacion = null): GpOrdenDespacho;
}
