<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\reservas\GpGuiaRemisionReserva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ReservationDeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createDeliveryNoteFromReservation($payload);
    public function sendReservationDeliveryNoteToGp($guide);

    public function generatePdfReservedDeliveryNote($payload);

    public function dispatchGuideReservation($payload): array|Collection|GpGuiaRemisionReserva|Model|null;
}
