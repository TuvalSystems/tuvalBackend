<?php

namespace App\Providers\Service;

use App\Services\Inventario\Bodegas\Contracts\DashboardServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteSalesTransferServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Inventario\Bodegas\DashboardService;
use App\Services\Inventario\Bodegas\DeliveryNoteCommonService;
use App\Services\Inventario\Bodegas\DeliveryNoteDispatchService;
use App\Services\Inventario\Bodegas\DeliveryNoteReplenishmentTransferService;
use App\Services\Inventario\Bodegas\DeliveryNoteSalesTransferService;
use App\Services\Inventario\Bodegas\DeliveryNoteService;
use App\Services\Inventario\Bodegas\DispatchService;
use App\Services\Inventario\Bodegas\LogisticsOperationsService;
use App\Services\Inventario\Bodegas\PurchaseReceiptLItemService;
use App\Services\Inventario\Bodegas\PurchaseReceiptService;
use App\Services\Inventario\Bodegas\ReservationDeliveryNoteService;
use App\Services\Inventario\Bodegas\TransferService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;


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
        DashboardServiceInterface::class                => DashboardService::class,
        DeliveryNoteDispatchServiceInterface::class     => DeliveryNoteDispatchService::class,
        DeliveryNoteSalesTransferServiceInterface::class => DeliveryNoteSalesTransferService::class,
        DeliveryNoteReplenishmentTransferServiceInterface::class => DeliveryNoteReplenishmentTransferService::class

    ];



    public function provides()
    {
        return array_keys($this->singletons);
    }

}
