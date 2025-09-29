<?php

namespace App\Providers\Service;

use App\Services\Pedidos\OrderService;
use App\Services\Venta\BillingService;
use Illuminate\Support\ServiceProvider;
use App\Services\Venta\QuotationService;
use App\Services\Pedidos\OrderJobService;
use App\Services\Venta\PurchaseOrderService;
use App\Services\Venta\InventoryQueryService;
use App\Services\Venta\Utils\OrderAndQuotationService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Venta\Contracts\BillingServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;

class VentaServiceProvider extends ServiceProvider
{

    public function register()
    {

        //aqui agrego las Cotizaciones para no crear otro provider
        $this->app->singleton(QuotationServiceInterface::class, QuotationService::class);
        /** servicio dedicdo a las ordenes de compra */
        $this->app->singleton(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);

        /** aqui va el servicio de consultas controller  */
        $this->app->singleton(InventoryQueryServiceInterface::class, InventoryQueryService::class);

        /** Servicios de utilidad para cotizacion y para pedidos  */

        $this->app->singleton(OrderAndQuotationServiceInterface::class, OrderAndQuotationService::class);


        // aqui agrego las ordenes para no crear otro provider
        $this->app->singleton(
            OrderServiceInterface::class,
            OrderService::class
        );

        //aqui va el servicio de Job de Pedidos
        // $this->app->singleton(OrderJobService::class);

        $this->app->singleton(BillingServiceInterface::class, BillingService::class);
    }




    public function boot()
    {
        //
    }
}
