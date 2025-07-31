<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Reposiciones\StockWarehouseService;
use App\Services\Reposiciones\TransferRepositionService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;

class ReposicionesServiceProvider extends ServiceProvider
{

    public function register()
    {

        //aqui agrego las transferencias para no crear otro provider

        $this->app->singleton(
            TransferRepositionServiceInterface::class,
            TransferRepositionService::class
        );

        $this->app->bind(
            StockWarehouseServiceInterface::class,
            StockWarehouseService::class
        );
    }


    public function boot()
    {

    }
}
