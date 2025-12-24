<?php

namespace App\Providers;

use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSalesService;
use App\Services\Api\ApiSeedBillingService;
use App\Services\Api\MswService;
use Illuminate\Support\ServiceProvider;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use Illuminate\Contracts\Support\DeferrableProvider;

class ApiServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        ApiGpService::class => ApiGpService::class,
        ApiSalesService::class => ApiSalesService::class,
        ApiSeedBillingService::class => ApiSeedBillingService::class,
        MswService::class => MswService::class,
        ApiDeliverNoteService::class => ApiDeliverNoteService::class,
    ];

    /**
     * Laravel solo cargará este archivo cuando pidas uno de estos servicios.
     * Esto evita leer configs y preparar clientes HTTP si no son necesarios.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }


    // public function register()
    // {


    //     $this->app->singleton(ApiGpService::class);
    //     $this->app->singleton(ApiSalesService::class);

    //     $this->app->singleton(ApiSeedBillingService::class);
    //     $this->app->singleton(MswService::class);

    //     /** servicio dedicado a las guias de remision */
    //     $this->app->singleton(ApiDeliverNoteService::class);
    // }



}
