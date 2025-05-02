<?php

namespace App\Providers;

use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSalesService;
use App\Services\Api\Core\SeedBellingHttpClient;
use Illuminate\Support\ServiceProvider;
use App\Services\Api\ApiSeedBellingService;
use App\Services\Api\Core\GpHttpClient;

class ApiServiceProvider extends ServiceProvider
{

    public $url;
    public $timeout;
    public function register()
    {



        $this->app->singleton(ApiGpService::class, function ($app) {
            return new ApiGpService();
        });
        $this->app->singleton(ApiSalesService::class, function ($app) {
            return new ApiSalesService();
        });

        $this->app->singleton(ApiSeedBellingService::class, function ($app) {
            return new ApiSeedBellingService();
        });

    }

    public function boot()
    {
        //
    }
}
