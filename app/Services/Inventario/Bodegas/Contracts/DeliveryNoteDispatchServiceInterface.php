<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpUsuario;

interface DeliveryNoteDispatchServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteFromDispatch(array $payload, GpUsuario $user);
     public function dispatchGuideTransferShipment(int $guiaId, GpUsuario $user);

     public function replicateDeliveryNoteDispatch(int $id): GpGuiaRemision ;
}
