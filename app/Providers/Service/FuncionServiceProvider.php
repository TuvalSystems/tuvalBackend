<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Funciones\InvoiceService;
use App\Services\Funciones\FunctionsService;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;

class FuncionServiceProvider extends ServiceProvider implements DeferrableProvider
{

    // public function register()
    // {
    //     //aqui va el servicio facturas de Pedidos
    //     $this->app->singleton(
    //         InvoiceServiceInterface::class,
    //         InvoiceService::class
    //     );


    //     //aqui va el sercvicio de function service de Fucionescontroller
    //     $this->app->singleton(
    //         FunctionsServiceInterface::class,
    //         FunctionsService::class
    //     );
    // }
    public array $singletons = [
        InvoiceServiceInterface::class => InvoiceService::class,
        FunctionsServiceInterface::class => FunctionsService::class,
    ];

    /**
     * Indica a Laravel que solo cargue este Provider si alguien pide
     * InvoiceServiceInterface o FunctionsServiceInterface.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
