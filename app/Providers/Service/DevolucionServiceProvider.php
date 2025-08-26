<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;

class DevolucionServiceProvider extends ServiceProvider implements DeferrableProvider
{

    public function register()
    {
        $this->app->singleton(EntryServiceInterface::class, EntryService::class);
        $this->app->singleton(DevolucionServiceInterface::class, DevolucionService::class);
    }


    public function boot() {}

    public function provides(): array
    {
        return [
            EntryServiceInterface::class,
            DevolucionServiceInterface::class,
        ];
    }
}
