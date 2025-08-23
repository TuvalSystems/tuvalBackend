<?php

namespace App\Services\Bodegas\Contracts;

interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
     public function getDeliveryNoteDetailByType(int|string $guiaId): array;
}
