<?php

namespace App\Providers\Service;

use App\Services\Aprobaciones\ApprovalQuotationService;
use App\Services\Aprobaciones\ApprovalReservationService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Aprobaciones\Utils\ApprovalFunctionsService;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;

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
        ApprovalReservationServiceInterface::class => ApprovalReservationService::class,
        ApprovalQuotationServiceInterface::class => ApprovalQuotationService::class,
        ApprovalFunctionsServiceInterface::class => ApprovalFunctionsService::class,
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
