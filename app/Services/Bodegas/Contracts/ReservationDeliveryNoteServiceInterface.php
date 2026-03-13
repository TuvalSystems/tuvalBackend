<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ReservationDeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createDeliveryNoteFromReservation($payload, GpUsuario $user);
    public function sendReservationDeliveryNoteToGp($guide);

    public function generatePdfReservedDeliveryNote($payload);

    public function dispatchGuideReservation($payload, GpUsuario $user): array|Collection|GpGuiaRemisionReserva|Model|null;

    public function getDetailReservationDeliveryNoteById($id): array;

    public function receiveItemsFromTransferred(int $guiaId, array $items, GpUsuario $user): GpGuiaRemisionReserva;

}
