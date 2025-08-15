<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\DevolucionJobService;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;

class DevolucionServiceProvider extends ServiceProvider
{

    public function register()
    {
           $this->app->bind(EntryServiceInterface::class, EntryService::class);
        $this->app->bind(DevolucionServiceInterface::class, DevolucionService::class);
        $this->app->bind(DevolucionJobService::class);
    }


    public function boot()
    {

    }
}
