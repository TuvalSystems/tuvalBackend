<?php

namespace App\Providers\Service;

use App\Services\Ventas\Pedidos\Importaciones\Contracts\OrdersImportInterface;
use App\Services\Ventas\Cotizaciones\Importaciones\Contracts\QuoteImportsInterface;
use App\Services\Ventas\Pedidos\Importaciones\OrdersImport;
use App\Services\Ventas\Cotizaciones\Importaciones\QuoteImports;
use App\Services\Ventas\Clientes\ClientCreatorService;
use App\Services\Ventas\Clientes\ClientGpSenderService;
use App\Services\Ventas\Clientes\Contracts\ClientCreatorServiceInterface;
use App\Services\Ventas\Clientes\Contracts\ClientGpSenderServiceInterface;
use App\Services\Ventas\Clientes\ClientQueryService;
use App\Services\Ventas\Clientes\Contracts\ClientQueryServiceInterface;
use App\Services\Ventas\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Ventas\Pedidos\OrderJobService;
use App\Services\Inventario\Despachos\Contracts\DispatchTypeServiceInterface;
use App\Services\Inventario\Despachos\DispatchTypeService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use App\Services\Ventas\Cotizaciones\QuotationService;
use App\Services\Compras\OrdenesCompra\PurchaseOrderService;
use App\Services\Inventario\Consultas\InventoryQueryService;
use App\Services\Ventas\Reservas\InventoryReservationService;
use App\Services\Ventas\Compartido\OrderAndQuotationService;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationServiceInterface;
use App\Services\Compras\OrdenesCompra\Contracts\PurchaseOrderServiceInterface;
use App\Services\Inventario\Consultas\Contracts\InventoryQueryServiceInterface;
use App\Services\Ventas\Reservas\Contracts\InventoryReservationServiceInterface;
use App\Services\Ventas\Pedidos\Contracts\OrderCreatorServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationCreatorServiceInterface;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuoteDraftServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\ProspectQuoteServiceInterface;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationHistoryCreatorServiceInterface;
use App\Services\Ventas\Cotizaciones\ProspectQuoteService;
use App\Services\Ventas\Cotizaciones\QuotationHistoryCreatorService;
use App\Services\Ventas\Pedidos\OrderCreatorService;
use App\Services\Ventas\Pedidos\OrderService;
use App\Services\Ventas\Pedidos\Contracts\InvoiceHistoryServiceInterface;
use App\Services\Ventas\Pedidos\InvoiceHistoryService;
use App\Services\Ventas\Pedidos\Contracts\OrderGuideServiceInterface;
use App\Services\Ventas\Pedidos\OrderGuideService;
use App\Services\Compras\Proveedores\ExternalFreightService;
use App\Services\Compras\Proveedores\Contracts\ExternalFreightServiceInterface;
use App\Services\Compras\Proveedores\Contracts\ProviderServiceInterface;
use App\Services\Compras\Proveedores\ProviderService;
use App\Services\Ventas\Cotizaciones\QuotationCreatorService;
use App\Services\Ventas\Cotizaciones\Borradores\QuoteDraftService;
use App\Services\Ventas\Compartido\Contracts\OrderAndQuotationServiceInterface;

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
        QuotationCreatorServiceInterface::class         => QuotationCreatorService::class,
        OrderCreatorServiceInterface::class             => OrderCreatorService::class,
        OrderJobService::class                          => OrderJobService::class,

        //importacion y actualizaciones en batch
        OrdersImportInterface::class                    => OrdersImport::class,
        QuoteImportsInterface::class                    => QuoteImports::class,
        QuoteDraftServiceInterface::class               => QuoteDraftService::class,


        //historico de cotizaciones
        QuotationHistoryCreatorServiceInterface::class  => QuotationHistoryCreatorService::class,

        // historico de pedidos
        InvoiceHistoryServiceInterface::class           => InvoiceHistoryService::class,
        //sp de pedidos
        OrderGuideServiceInterface::class               => OrderGuideService::class,

        //para el manejo de tipo de despachos
        DispatchTypeServiceInterface::class             => DispatchTypeService::class,
        ProviderServiceInterface::class                 => ProviderService::class,
        ExternalFreightServiceInterface::class            => ExternalFreightService::class,

        ClientCreatorServiceInterface::class            => ClientCreatorService::class,
        ClientGpSenderServiceInterface::class           => ClientGpSenderService::class,

        ProspectQuoteServiceInterface::class            => ProspectQuoteService::class,
    ];


    public function provides()
    {
        return array_keys($this->singletons);
    }
}
