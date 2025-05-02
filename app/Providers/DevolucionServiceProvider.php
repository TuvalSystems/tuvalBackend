<?php

namespace App\Providers;

use App\Http\Controllers\WebserviceController;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Devoluciones\DevolucionJobService;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Services\Reposiciones\TransferRepositionService;
use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Pedidos\OrderService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\StockWarehouseService;


class DevolucionServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton(EntryServiceInterface::class,EntryService::class);
        $this->app->singleton(DevolucionServiceInterface::class, DevolucionService::class);
        $this->app->bind(DevolucionJobService::class, function ($app) {
            return new DevolucionJobService(
                $app->make(WebserviceController::class),
                $app->make(ApiGpReturnsIntegrationService::class)
            );
        });

        //aqui agrego las transferencias para no crear otro provider

        $this->app->bind(
            TransferRepositionServiceInterface::class,
            TransferRepositionService::class
        );

        $this->app->bind(
            StockWarehouseServiceInterface::class,
            StockWarehouseService::class
        );


        // aqui agrego las ordenes para no crear otro provider
        $this->app->bind(
            OrderServiceInterface::class,
            OrderService::class
        );
    }


    public function boot()
    {

    }
}
