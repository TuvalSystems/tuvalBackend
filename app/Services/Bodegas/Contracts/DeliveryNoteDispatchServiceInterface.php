<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;

interface DeliveryNoteDispatchServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteFromDispatch($payload, GpUsuario $user);
     public function dispatchGuideTransferShipment(int $guiaId, GpUsuario $user);
}
