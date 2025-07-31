<?php

namespace App\Providers\Service\SqlServer;

use Illuminate\Support\ServiceProvider;
use App\Services\SqlServer\Ventas\ClientService;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;

class VentaSqlServerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
         /** servicio de clienteSer */
          $this->app->singleton(
            ClientServiceInterface::class,
            ClientService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
