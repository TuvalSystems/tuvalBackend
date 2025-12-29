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

    // public function register()
    // {

    //     //aqui agrego las transferencias para no crear otro provider

    //     $this->app->singleton(
    //         TransferRepositionServiceInterface::class,
    //         TransferRepositionService::class
    //     );

    //     $this->app->singleton(
    //         StockWarehouseServiceInterface::class,
    //         StockWarehouseService::class
    //     );
    //     $this->app->singleton(ItemsLService::class);
    // }

    public array $singletons = [
        // Interfaz => Implementación
        TransferRepositionServiceInterface::class => TransferRepositionService::class,
        StockWarehouseServiceInterface::class     => StockWarehouseService::class,

        // Clase directa (sin interfaz)
        // Se mapea contra sí misma para que Laravel sepa que debe tratarla como Singleton
        ItemsLService::class                      => ItemsLService::class,
    ];

    /**
     * Devuelve las claves del array anterior.
     * Esto incluye tanto las interfaces como la clase ItemsLService.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }




}
