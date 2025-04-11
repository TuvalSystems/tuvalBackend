<?php

namespace App\Providers;

use App\Services\ApiGpService;
use App\Services\ApiSalesService;
use App\Services\ApiSeedBellingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ApiGpService::class, function () {
            return new ApiGpService();
        });
        $this->app->singleton(ApiSeedBellingService::class, function () {
            return new ApiSeedBellingService();
        });

        $this->app->singleton(ApiSalesService::class,  function () {
            return new ApiSalesService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
