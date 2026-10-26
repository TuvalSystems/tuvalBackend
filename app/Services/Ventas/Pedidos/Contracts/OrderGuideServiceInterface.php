<?php

namespace App\Services\Ventas\Pedidos\Contracts;

interface OrderGuideServiceInterface
{
    // Define los métodos

    public function getOrdersWithGuidesByPeriod(int $period, ?string $pvCode, ?string $dispacth = null): array;
    public function fetchListDeliveryNoteSp(int $period, string $warehouse, ?string $numGuide = null): array;
    public function fetchListdeliveryNoteHeader(int $period, string $warehouse, ?string $numGuide = null): array;
}
