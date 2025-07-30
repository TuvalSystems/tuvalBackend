<?php

namespace App\Providers;

use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Pedidos\OrderService;
use Illuminate\Support\ServiceProvider;
use App\Services\Venta\QuotationService;
use App\Services\Bodegas\DispatchService;
use App\Services\Pedidos\OrderJobService;
use App\Services\Funciones\InvoiceService;
use App\Services\Devoluciones\EntryService;
use App\Services\Funciones\FunctionsService;
use App\Services\Venta\PurchaseOrderService;
use App\Services\Venta\InventoryQueryService;
use App\Services\Aprobaciones\ApprovalService;
use App\Services\Bodegas\PurchaseReceiptService;
use App\Services\Devoluciones\DevolucionService;
use App\Services\Notificacion\NotificationService;
use App\Services\Devoluciones\DevolucionJobService;
use App\Services\Reposiciones\StockWarehouseService;
use App\Services\Bodegas\PurchaseReceiptLItemService;
use App\Services\Venta\Utils\OrderAndQuotationService;
use App\Services\Reposiciones\TransferRepositionService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Bodegas\DeliveryNoteService;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;

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

        $this->app->bind(
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

        //aqui va el sercvicio de function service de Fucionescontroller
        $this->app->singleton(
            FunctionsServiceInterface::class,
            FunctionsService::class
        );

        //aqui agrego las ordenes de compra recibidas para no crear otro provider

        $this->app->singleton(
            PurchaseReceiptServiceInterface::class,
            PurchaseReceiptService::class
        );

        /** servicio dedicado a las ordenes de despacho */
        $this->app->singleton(DispatchServiceInterface::class, DispatchService::class);

        //aqui agrego las Cotizaciones para no crear otro provider
        $this->app->bind(QuotationServiceInterface::class, QuotationService::class);

        /** servicio dedicado a las notificciones */
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);


        /** servicio dedicdo a las ordenes de compra */
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);

        //aqui va el servicio de Job de Pedidos
        $this->app->bind(OrderJobService::class);

        //aqui va el servicio facturas de Pedidos
        $this->app->singleton(
            InvoiceServiceInterface::class,
            InvoiceService::class
        );

        /** aqui va el servicio de consultas controller  */
        $this->app->bind(InventoryQueryServiceInterface::class, InventoryQueryService::class);

        /** Servicios de utilidad para cotizacion y para pedidos  */

        $this->app->singleton(OrderAndQuotationServiceInterface::class, OrderAndQuotationService::class);

        /**Servicio para utilizar para los recibidos de Item L  */
        $this->app->bind(PurchaseReceiptLItemServiceInterface::class, PurchaseReceiptLItemService::class);

        /** servicio dedicadoa las guias de remision  */
        $this->app->bind(DeliveryNoteServiceInterface::class, DeliveryNoteService::class);
    }


    public function boot() {}
}
