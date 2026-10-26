<?php

namespace App\Services\Ventas\Pedidos\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface OrderCreatorServiceInterface
{
    public function createOrder(array $data, GpUsuario $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): array;
}
