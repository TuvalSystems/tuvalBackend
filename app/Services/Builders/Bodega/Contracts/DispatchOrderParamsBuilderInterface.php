<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;

interface DispatchOrderParamsBuilderInterface
{
   public function build(GpPedido $pedido, string $estado, ?string $fechaLiberacion = null): GpOrdenDespacho;
}
