<?php

namespace App\Providers;

use App\Services\ApiGpService;
use App\Services\ApiSeedBellingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public $baseUrl; // Initialize with default values or set them dynamically
    public $timeout ;
    public $endpoints = [];
    public $token;
    public $idSuscriptor;

    public function register()
    {
        $this->app->singleton(ApiGpService::class, function () {
            return new ApiGpService($this->baseUrl, $this->timeout, $this->endpoints);
        });
        $this->app->singleton(ApiSeedBellingService::class, function () {
            return new ApiSeedBellingService($this->baseUrl, $this->timeout, $this->endpoints, $this->token, $this->idSuscriptor);
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
