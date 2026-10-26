<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Admin\Usuarios\GpUsuario;

interface DeliveryNoteDispatchServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function createDeliveryNoteFromDispatch(array $payload, GpUsuario $user): GpGuiaRemision;
    public function dispatchGuideTransferShipment(int $guiaId, GpUsuario $user): array;

    public function replicateDeliveryNoteDispatch(int $id): GpGuiaRemision;
}
