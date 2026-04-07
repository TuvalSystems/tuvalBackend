<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\ReturnCancellationService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Devoluciones\Contracts\ReturnCancellationServiceInterface;
use App\Services\Devoluciones\DevolucionJobService;
use App\Services\Devoluciones\ReInvoiceService;

class DevolucionServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        EntryServiceInterface::class => EntryService::class,
        DevolucionServiceInterface::class => DevolucionService::class,
        ReturnCancellationServiceInterface::class => ReturnCancellationService::class,
        ReInvoiceServiceInterface::class => ReInvoiceService::class,

        DevolucionJobService::class
    ];
   

    public function provides()
    {
        return array_keys($this->singletons);
    }



}
