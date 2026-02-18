<?php

namespace App\Services\Bodegas\Contracts;

interface DeliveryNoteDispatchServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteFromDispatch($payload);
}
