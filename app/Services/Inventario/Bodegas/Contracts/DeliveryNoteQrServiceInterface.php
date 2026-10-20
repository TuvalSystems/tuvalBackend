<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;

interface DeliveryNoteQrServiceInterface
{
    public function code(GpGuiaRemision|GpGuiaRemisionReserva $guide): string;

    public function decode(string $code): array;

    public function image(GpGuiaRemision|GpGuiaRemisionReserva $guide): string;
}
