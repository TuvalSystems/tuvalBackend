<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface DeliveryNoteSalesTransferServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);

    public function dispatchDeliveryNoteSalesTransfer(int $guiaId, GpUsuario $user);

    public function generatePdfDeliveryNotesSalesTransfer(array $payload);
    public function receiveDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);
}
