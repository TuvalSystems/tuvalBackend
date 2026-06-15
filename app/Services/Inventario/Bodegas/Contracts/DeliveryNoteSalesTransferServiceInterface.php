<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\GpUsuario;

interface DeliveryNoteSalesTransferServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);

    public function dispatchDeliveryNoteSalesTransfer(int $guiaId, GpUsuario $user);

     public function generatePdfDeliveryNotesSalesTransfer(array $payload);
    public function receiveDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);
}
