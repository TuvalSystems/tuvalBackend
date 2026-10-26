<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;

interface DeliveryNoteQrServiceInterface
{
    public function code(GpGuiaRemision|GpGuiaRemisionReserva $guide): string;

    public function decode(string $code): array;

    public function image(GpGuiaRemision|GpGuiaRemisionReserva $guide): string;
}
