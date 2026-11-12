<?php

declare(strict_types=1);

namespace App\Providers\Service;

use App\Services\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryServiceInterface;
use App\Services\Reportes\Inventory\ReplenishmentReceiptHistoryService;
use App\Services\Reportes\CommercialApprovalsService;
use App\Services\Reportes\Contracts\CommercialApprovalsServiceInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ReportesServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public array $singletons = [
        CommercialApprovalsServiceInterface::class => CommercialApprovalsService::class,
        ReplenishmentReceiptHistoryServiceInterface::class => ReplenishmentReceiptHistoryService::class,
    ];

    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
