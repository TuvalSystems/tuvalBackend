<?php

namespace App\Providers\Service\SqlServer;

use Illuminate\Support\ServiceProvider;
use App\Services\SqlServer\Cobranza\CreditValidationService;
use App\Services\SqlServer\Cobranza\Contracts\CreditValidationServiceInterface;

class SqlServerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            CreditValidationServiceInterface::class,
            CreditValidationService::class
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
