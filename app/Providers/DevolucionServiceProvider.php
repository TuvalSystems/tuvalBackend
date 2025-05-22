<?php

namespace App\Providers;

use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Bodegas\PurchaseReceiptService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\FunctionsService;
use App\Services\Pedidos\OrderService;
use App\Services\Venta\QuotationService;
use Illuminate\Support\ServiceProvider;
use App\Services\Devoluciones\EntryService;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Devoluciones\DevolucionJobService;
use App\Services\Reposiciones\StockWarehouseService;
use App\Services\Reposiciones\TransferRepositionService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;

class DevolucionServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(EntryServiceInterface::class, EntryService::class);
        $this->app->bind(DevolucionServiceInterface::class, DevolucionService::class);
        $this->app->bind(DevolucionJobService::class);

        //aqui agrego las transferencias para no crear otro provider

        $this->app->singleton(
            TransferRepositionServiceInterface::class,
            TransferRepositionService::class
        );

        $this->app->singleton(
            StockWarehouseServiceInterface::class,
            StockWarehouseService::class
        );


        // aqui agrego las ordenes para no crear otro provider
        $this->app->singleton(
            OrderServiceInterface::class,
            OrderService::class
        );

        // aqui agrego las aprobaciones para no crear otro provider
        $this->app->singleton(
            ApprovalServiceInterface::class,
            ApprovalService::class
        );

        //aqui agrego las funciones para no crear otro provider
        $this->app->singleton(
            FunctionsServiceInterface::class,
            FunctionsService::class
        );

        //aqui agrego las RecibidosdeCompraL para no crear otro provider
        $this->app->singleton(
            PurchaseReceiptServiceInterface::class,
            PurchaseReceiptService::class
        );

        //aqui agrego las Cotizaciones para no crear otro provider
        $this->app->singleton(QuotationServiceInterface::class, QuotationService::class);
    }


    public function boot() {}
}
