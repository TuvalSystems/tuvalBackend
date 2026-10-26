<?php

namespace App\Providers\Service;

use App\Services\Inventario\Articulos\ArticleSegmentConfigService;
use App\Services\Inventario\Articulos\ArticleService;
use App\Services\Inventario\Articulos\ArticleSyncService;
use App\Services\Inventario\Articulos\Contracts\ArticleSegmentConfigServiceInterface;
use App\Services\Inventario\Articulos\Contracts\ArticleServiceInterface;
use App\Services\Inventario\Articulos\Contracts\ArticleSyncServiceInterface;
use App\Services\Inventario\Dashboard\Contracts\DashboardServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteMediaServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNotePurchaseOrderServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteQrServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteSalesTransferServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteSignaturePdfServiceInterface;
use App\Services\Inventario\Despachos\Contracts\DispatchServiceInterface;
use App\Services\Inventario\Despachos\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Inventario\GuiasRemision\Contracts\ReceivedDeliveryNoteServiceInterface;
use App\Services\Inventario\GuiasRemision\Reservas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Inventario\Transferencias\Contracts\TransferServiceInterface;
use App\Services\Inventario\Dashboard\DashboardService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteCommonService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteDispatchService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteMediaService;
use App\Services\Inventario\GuiasRemision\DeliveryNotePurchaseOrderService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteQrService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteReplenishmentTransferService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteSalesTransferService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteService;
use App\Services\Inventario\GuiasRemision\DeliveryNoteSignaturePdfService;
use App\Services\Inventario\Despachos\DispatchService;
use App\Services\Inventario\Despachos\LogisticsOperationsService;
use App\Services\Compras\Recepciones\PurchaseReceiptLItemService;
use App\Services\Compras\Recepciones\PurchaseReceiptService;
use App\Services\Inventario\GuiasRemision\ReceivedDeliveryNoteService;
use App\Services\Inventario\GuiasRemision\Reservas\ReservationDeliveryNoteService;
use App\Services\Inventario\Transferencias\TransferService;
use App\Services\Inventario\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Services\Inventario\Reposiciones\ItemsLService;
use App\Services\Inventario\Reposiciones\TransferRepositionService;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Inventario\Stock\StockWarehouseService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;


class BodegasServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        DeliveryNotePurchaseOrderServiceInterface::class           => DeliveryNotePurchaseOrderService::class,
        DeliveryNoteQrServiceInterface::class                       => DeliveryNoteQrService::class,
        DeliveryNoteSignaturePdfServiceInterface::class             => DeliveryNoteSignaturePdfService::class,
        DeliveryNoteMediaServiceInterface::class                    => DeliveryNoteMediaService::class,
        ReceivedDeliveryNoteServiceInterface::class                 => ReceivedDeliveryNoteService::class,
        DispatchServiceInterface::class                             => DispatchService::class,
        PurchaseReceiptServiceInterface::class                      => PurchaseReceiptService::class,
        PurchaseReceiptLItemServiceInterface::class                 => PurchaseReceiptLItemService::class,
        DeliveryNoteServiceInterface::class                         => DeliveryNoteService::class,
        LogisticsOperationsServiceInterface::class                  => LogisticsOperationsService::class,
        TransferServiceInterface::class                             => TransferService::class,
        ReservationDeliveryNoteServiceInterface::class              => ReservationDeliveryNoteService::class,
        DeliveryNoteCommonServiceInterface::class                   => DeliveryNoteCommonService::class,
        DashboardServiceInterface::class                            => DashboardService::class,
        DeliveryNoteDispatchServiceInterface::class                 => DeliveryNoteDispatchService::class,
        DeliveryNoteSalesTransferServiceInterface::class            => DeliveryNoteSalesTransferService::class,
        DeliveryNoteReplenishmentTransferServiceInterface::class    => DeliveryNoteReplenishmentTransferService::class,
        TransferRepositionServiceInterface::class                   => TransferRepositionService::class,
        StockWarehouseServiceInterface::class                       => StockWarehouseService::class,
        ItemsLService::class                                       => ItemsLService::class,
        ArticleSyncServiceInterface::class                          => ArticleSyncService::class,
        ArticleSegmentConfigServiceInterface::class                 => ArticleSegmentConfigService::class,
        ArticleServiceInterface::class                              => ArticleService::class
    ];



    public function provides()
    {
        return array_keys($this->singletons);
    }
}
