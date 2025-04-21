<?php

namespace App\Providers;

use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSalesService;
use Illuminate\Support\ServiceProvider;
use App\Services\Api\ApiSeedBellingService;

class ApiServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton(ApiGpService::class);
        $this->app->singleton(ApiSalesService::class);
        $this->app->singleton(ApiSeedBellingService::class);
    }

    public function boot()
    {
        //
    }
}
