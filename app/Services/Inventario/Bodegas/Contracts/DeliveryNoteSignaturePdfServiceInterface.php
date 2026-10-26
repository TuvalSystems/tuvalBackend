<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;

interface DeliveryNoteSignaturePdfServiceInterface
{
    public function forGuide(GpGuiaRemision|GpGuiaRemisionReserva $guide): ?array;
}
