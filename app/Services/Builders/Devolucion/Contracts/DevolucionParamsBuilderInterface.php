<?php

namespace App\Services\Builders\Devolucion\Contracts;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpPedido;

interface DevolucionParamsBuilderInterface
{


     public function formPayloadDevolutionHeader(array $data, array $calculatedData): GpDevoluciones;
     public function formPayloadDevolutionDetail(int $devolucionId, array $details, bool $dispatched): GpDevolucionesDetalle;

    // Define los métodos que debe implementar el servicio
    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, $userId, $items);
}
