<?php

namespace App\Services\Inventario\GuiasRemision\Reservas\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
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
