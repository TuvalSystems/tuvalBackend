<?php

namespace App\Providers\Service;

use App\Services\Venta\ClientQueryService;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Services\Venta\OrderJobService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Venta\QuotationService;
use App\Services\Venta\PurchaseOrderService;
use App\Services\Venta\InventoryQueryService;
use App\Services\Venta\InventoryReservationService;
use App\Services\Venta\Utils\OrderAndQuotationService;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Services\Venta\Contracts\QuotationCartServiceInterface;
use App\Services\Venta\Contracts\QuotationCreatorServiceInterface;
use App\Services\Venta\OrderService;
use App\Services\Venta\QuotationCartService;
use App\Services\Venta\QuotationCreatorService;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;

class VentaServiceProvider extends ServiceProvider implements DeferrableProvider
{




    public array $singletons = [
        QuotationServiceInterface::class                => QuotationService::class,
        PurchaseOrderServiceInterface::class            => PurchaseOrderService::class,
        InventoryQueryServiceInterface::class           => InventoryQueryService::class,
        OrderAndQuotationServiceInterface::class        => OrderAndQuotationService::class,
        OrderServiceInterface::class                    => OrderService::class,
        InventoryReservationServiceInterface::class     => InventoryReservationService::class,
        ClientQueryServiceInterface::class              => ClientQueryService::class,
        QuotationCartServiceInterface::class            => QuotationCartService::class,
        QuotationCreatorServiceInterface::class         => QuotationCreatorService::class,

        // Nota: Si descomentas OrderJobService en el futuro, agrégalo aquí:
        OrderJobService::class ,
    ];

    /**
     * Indica a Laravel qué servicios provee este archivo para que sepa
     * cuándo cargarlo.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
