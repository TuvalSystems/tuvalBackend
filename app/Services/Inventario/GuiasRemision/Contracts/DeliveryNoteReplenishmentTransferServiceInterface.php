<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface DeliveryNoteReplenishmentTransferServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user);
    public function dispatchDeliveryNoteReplenishmentTransfer(int $guiaId, GpUsuario $user);
    public function receiveDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user): string;
}
