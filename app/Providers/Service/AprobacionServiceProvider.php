<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;

class AprobacionServiceProvider extends ServiceProvider implements DeferrableProvider
{

    // public function register()
    // {
    //     // aqui agrego las aprobaciones para no crear otro provider
    //     $this->app->singleton(
    //         ApprovalServiceInterface::class,
    //         ApprovalService::class
    //     );
    // }


    public array $singletons = [
        ApprovalServiceInterface::class => ApprovalService::class,
    ];

    /**
     * Laravel solo despertará este Provider cuando alguien pida
     * ApprovalServiceInterface.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
