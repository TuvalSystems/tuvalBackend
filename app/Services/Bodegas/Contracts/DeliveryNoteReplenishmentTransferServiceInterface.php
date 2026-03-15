<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;

interface DeliveryNoteReplenishmentTransferServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user);
    public function dispatchDeliveryNoteReplenishmentTransfer(int $guiaId, GpUsuario $user);
    public function receiveDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user): string;
}
