<?php

namespace App\Providers\Service\SqlServer;

use Illuminate\Support\ServiceProvider;
use App\Services\SqlServer\Bodega\StockSqlService;
use App\Services\SqlServer\Bodega\Contracts\StockSqlServiceInterface;

class BodegaSqlServerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            StockSqlServiceInterface::class,
            StockSqlService::class
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
