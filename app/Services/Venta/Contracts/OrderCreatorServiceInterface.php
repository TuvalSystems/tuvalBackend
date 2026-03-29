<?php

namespace App\Services\Venta\Contracts;

interface OrderCreatorServiceInterface
{
    public function createOrder(array $data, $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): array;
}
