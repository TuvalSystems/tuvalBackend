<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;

interface DeliveryNoteSalesTransferServiceInterface
{
    // Define los métodos que debe implementar el servicio
     public function createDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);

      public function receiveDeliveryNoteSalesTransfer(array $payload, GpUsuario $user);
}
