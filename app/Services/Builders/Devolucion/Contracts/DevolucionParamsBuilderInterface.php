<?php

namespace App\Services\Builders\Devolucion\Contracts;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpPedido;

interface DevolucionParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, $userId, $items);
}
