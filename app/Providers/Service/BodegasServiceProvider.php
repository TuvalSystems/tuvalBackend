<?php

namespace App\Providers\Service;

use App\Services\Bodegas\Contracts\DashboardServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Bodegas\DispatchService;
use App\Services\Bodegas\DeliveryNoteService;
use App\Services\Bodegas\PurchaseReceiptService;
use App\Services\Bodegas\PurchaseReceiptLItemService;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Bodegas\DashboardService;
use App\Services\Bodegas\DeliveryNoteCommonService;
use App\Services\Bodegas\LogisticsOperationsService;
use App\Services\Bodegas\ReservationDeliveryNoteService;
use App\Services\Bodegas\TransferService;

class BodegasServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        DispatchServiceInterface::class                 => DispatchService::class,
        PurchaseReceiptServiceInterface::class          => PurchaseReceiptService::class,
        PurchaseReceiptLItemServiceInterface::class     => PurchaseReceiptLItemService::class,
        DeliveryNoteServiceInterface::class             => DeliveryNoteService::class,
        LogisticsOperationsServiceInterface::class      => LogisticsOperationsService::class,
        TransferServiceInterface::class                 => TransferService::class,
        ReservationDeliveryNoteServiceInterface::class  => ReservationDeliveryNoteService::class,
        DeliveryNoteCommonServiceInterface::class       => DeliveryNoteCommonService::class,
        DashboardServiceInterface::class                => DashboardService::class
    ];



    public function provides()
    {
        return array_keys($this->singletons);
    }

}
