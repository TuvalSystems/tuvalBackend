<?php

namespace App\Providers;

use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSalesService;
use App\Services\Api\MswService;
use Illuminate\Support\ServiceProvider;
use App\Services\Api\ApiSeedBellingService;

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

        $this->app->singleton(MswService::class);
    }


       public function boot(){}
}
