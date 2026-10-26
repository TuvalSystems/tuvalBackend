<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Facturacion\InvoiceService;
use App\Services\Compartido\FunctionsService;
use App\Services\Facturacion\Contracts\InvoiceServiceInterface;
use App\Services\Compartido\Contracts\FunctionsServiceInterface;

class FuncionServiceProvider extends ServiceProvider implements DeferrableProvider
{

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
