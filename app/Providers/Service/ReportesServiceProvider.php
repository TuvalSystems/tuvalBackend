<?php

declare(strict_types=1);

namespace App\Providers\Service;

use App\Services\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryServiceInterface;
use App\Services\Reportes\Inventory\ReplenishmentReceiptHistoryService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ReportesServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public array $singletons = [
        ReplenishmentReceiptHistoryServiceInterface::class => ReplenishmentReceiptHistoryService::class,
    ];

    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
