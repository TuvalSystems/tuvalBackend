<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Reposiciones\StockWarehouseService;
use App\Services\Reposiciones\TransferRepositionService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Services\Reposiciones\ItemsLService;

class ReposicionesServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        // Interfaz => Implementación
        TransferRepositionServiceInterface::class => TransferRepositionService::class,
        StockWarehouseServiceInterface::class     => StockWarehouseService::class,
        ItemsLService::class                      => ItemsLService::class,
    ];

   
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
