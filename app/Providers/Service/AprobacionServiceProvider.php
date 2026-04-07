<?php

namespace App\Providers\Service;

use App\Services\Aprobaciones\ApprovalDevolutionService;
use App\Services\Aprobaciones\ApprovalOrderService;
use App\Services\Aprobaciones\ApprovalQuotationService;
use App\Services\Aprobaciones\ApprovalReservationService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Aprobaciones\Contracts\ApprovalDevolutionServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Aprobaciones\Utils\ApprovalFunctionsService;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;

class AprobacionServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public array $singletons = [
        ApprovalServiceInterface::class             => ApprovalService::class,
        ApprovalReservationServiceInterface::class  => ApprovalReservationService::class,
        ApprovalQuotationServiceInterface::class    => ApprovalQuotationService::class,
        ApprovalFunctionsServiceInterface::class    => ApprovalFunctionsService::class,
        ApprovalOrderServiceInterface::class        => ApprovalOrderService::class,
        ApprovalDevolutionServiceInterface::class   => ApprovalDevolutionService::class
    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
