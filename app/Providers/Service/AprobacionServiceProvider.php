<?php

namespace App\Providers\Service;

use App\Services\Devoluciones\Aprobaciones\ApprovalDevolutionService;
use App\Services\Ventas\Aprobaciones\ApprovalOrderService;
use App\Services\Ventas\Aprobaciones\ApprovalQuotationService;
use App\Services\Ventas\Aprobaciones\ApprovalReservationService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Ventas\Aprobaciones\ApprovalService;
use App\Services\Devoluciones\Aprobaciones\Contracts\ApprovalDevolutionServiceInterface;
use App\Services\Ventas\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Ventas\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Ventas\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Ventas\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Ventas\Aprobaciones\Utils\ApprovalFunctionsService;
use App\Services\Ventas\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;

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
