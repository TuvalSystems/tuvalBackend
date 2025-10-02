<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;

interface ReservedBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function fromPayloadReserved(array $data,  GpUsuario $user);
}
