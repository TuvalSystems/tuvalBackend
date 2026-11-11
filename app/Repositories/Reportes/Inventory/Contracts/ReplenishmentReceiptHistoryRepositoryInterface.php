<?php

declare(strict_types=1);

namespace App\Repositories\Reportes\Inventory\Contracts;

interface ReplenishmentReceiptHistoryRepositoryInterface
{
    public function getHistory(string $period, ?string $replenishmentNumber = null): array;
}
