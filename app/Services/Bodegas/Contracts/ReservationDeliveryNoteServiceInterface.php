<?php

namespace App\Services\Bodegas\Contracts;

interface ReservationDeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createDeliveryNoteFromReservation($payload);
    public function sendReservationDeliveryNoteToGp($guide);

    public function generatePdfReservedDeliveryNote($payload);
}
