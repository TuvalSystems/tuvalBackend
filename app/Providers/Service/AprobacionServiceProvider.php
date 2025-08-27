<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;

class AprobacionServiceProvider extends ServiceProvider
{

    public function register()
    {
        // aqui agrego las aprobaciones para no crear otro provider
        $this->app->singleton(
            ApprovalServiceInterface::class,
            ApprovalService::class
        );
    }


    public function boot(){}
}
