<?php

declare(strict_types=1);

namespace App\Services\Reportes\Inventory\Contracts;

interface ReplenishmentReceiptHistoryServiceInterface
{
    public function getHistory(string $period, ?string $replenishmentNumber = null): array;
}
