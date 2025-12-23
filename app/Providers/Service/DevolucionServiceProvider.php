<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\ReturnCancellationService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\ReturnCancellationServiceInterface;

class DevolucionServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton(EntryServiceInterface::class, EntryService::class);
        $this->app->singleton(DevolucionServiceInterface::class, DevolucionService::class);
        $this->app->singleton(ReturnCancellationServiceInterface::class, ReturnCancellationService::class);
    }


    public function boot() {}


}
