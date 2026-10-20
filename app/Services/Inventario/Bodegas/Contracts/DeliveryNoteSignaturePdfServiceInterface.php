<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;

interface DeliveryNoteSignaturePdfServiceInterface
{
    public function forGuide(GpGuiaRemision|GpGuiaRemisionReserva $guide): ?array;
}
