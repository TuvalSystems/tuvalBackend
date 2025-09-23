<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Reposiciones\StockWarehouseService;
use App\Services\Reposiciones\TransferRepositionService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Services\Reposiciones\ItemsLService;

class ReposicionesServiceProvider extends ServiceProvider
{

    public function register()
    {

        //aqui agrego las transferencias para no crear otro provider

        $this->app->singleton(
            TransferRepositionServiceInterface::class,
            TransferRepositionService::class
        );

        $this->app->singleton(
            StockWarehouseServiceInterface::class,
            StockWarehouseService::class
        );
        $this->app->singleton(ItemsLService::class);
    }




    public function boot() {}
}
