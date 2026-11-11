<?php

declare(strict_types=1);

namespace App\Services\Reportes\Inventory;

use App\Repositories\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryRepositoryInterface;
use App\Services\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryServiceInterface;

class ReplenishmentReceiptHistoryService implements ReplenishmentReceiptHistoryServiceInterface
{
    public function __construct(
        private readonly ReplenishmentReceiptHistoryRepositoryInterface $repository,
    ) {}

    public function getHistory(string $period, ?string $replenishmentNumber = null): array
    {
        return $this->repository->getHistory($period, $replenishmentNumber);
    }
}
