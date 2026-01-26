<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpPedido;

interface OrderCreatorServiceInterface
{
     public function createOrder(array $data, $user, array $existingImages = [], array $newImages = []): GpPedido;
}
