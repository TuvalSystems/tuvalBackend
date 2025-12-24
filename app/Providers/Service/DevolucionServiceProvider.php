<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\ReturnCancellationService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\ReturnCancellationServiceInterface;
use App\Services\Devoluciones\DevolucionJobService;

class DevolucionServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        EntryServiceInterface::class => EntryService::class,
        DevolucionServiceInterface::class => DevolucionService::class,
        ReturnCancellationServiceInterface::class => ReturnCancellationService::class,

        DevolucionJobService::class
    ];
    // public function register()
    // {
    //     $this->app->singleton(EntryServiceInterface::class, EntryService::class);
    //     $this->app->singleton(DevolucionServiceInterface::class, DevolucionService::class);
    //     $this->app->singleton(ReturnCancellationServiceInterface::class, ReturnCancellationService::class);
    // }

    public function provides()
    {
        return array_keys($this->singletons);
    }



}
