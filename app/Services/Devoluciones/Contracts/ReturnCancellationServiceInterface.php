<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\Devoluciones\GpDevoluciones;

interface ReturnCancellationServiceInterface
{
    // Define los métodos que debe implementar el servicio
     public function execute(array $order, GpDevoluciones $devolucion);
}
