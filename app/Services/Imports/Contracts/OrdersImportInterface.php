<?php

namespace App\Services\Imports\Contracts;

interface OrdersImportInterface
{
    // Define los métodos que debe implementar el servicio

    public function getOrdersByUpdate(string $start, string $end);
    public function getOrdersDetailByUpdate(string $start, string $end);
    public function updateSqlServerOrdersFromPayload(array $payload);
    public function updateSqlServerOrderDetailsFromPayload(array $payload);
    public function markOrdersAsInProcess(array $ids): int;
    public function markOrderDetailsAsInProcess(array $ids): int;
}
