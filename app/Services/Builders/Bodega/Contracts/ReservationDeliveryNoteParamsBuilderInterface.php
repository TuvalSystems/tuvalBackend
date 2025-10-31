<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;

interface ReservationDeliveryNoteParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function buildReservationGuideHeader($payload, GpUsuario $user, array $datavehicle, string $secuencial, string $dirDestination, string $numGuide, string $bodGuide, string $dirOrigin): GpGuiaRemisionReserva;
    public function buildReservationGuideDetails($data, int $guiaId) : GpGuiaRemisionReservaDetalle;
}
