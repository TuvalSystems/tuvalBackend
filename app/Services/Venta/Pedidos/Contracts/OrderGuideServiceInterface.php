<?php
namespace App\Services\Venta\Pedidos\Contracts;

interface OrderGuideServiceInterface
{
    // Define los métodos

    public function getOrdersWithGuidesByPeriod(int $period, ?string $pvCode, ?string $dispacth = null): array;
    public function fetchListDeliveryNoteSp(int $period, string $warehouse, ?string $numGuide = null) : array;
}
