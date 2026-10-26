<?php

namespace App\Providers\Service\Builder;

use Illuminate\Support\ServiceProvider;
use App\Services\Ventas\Pedidos\Builders\OrderBuilder;
use App\Services\Ventas\Clientes\Builders\ClientBuilder;
use App\Services\Ventas\Reservas\Builders\ReservedBuilder;
use App\Services\Ventas\Cotizaciones\Builders\QuotationBuilder;
use Illuminate\Contracts\Support\DeferrableProvider;
use App\Services\Inventario\GuiasRemision\Builders\DeliveryNoteParamsBuilder;
use App\Services\Devoluciones\Builders\DevolucionParamsBuilder;
use App\Services\Ventas\Pedidos\Builders\Contracts\OrderBuilderInterface;
use App\Services\Ventas\Clientes\Builders\Contracts\ClientBuilderInterface;
use App\Services\Ventas\Reservas\Builders\Contracts\ReservedBuilderInterface;
use App\Services\Ventas\Cotizaciones\Builders\Contracts\QuotationBuilderInterface;
use App\Services\Inventario\GuiasRemision\Builders\ReservationDeliveryNoteParamsBuilder;
use App\Services\Inventario\GuiasRemision\Builders\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Devoluciones\Builders\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Inventario\GuiasRemision\Builders\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Inventario\GuiasRemision\Builders\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use App\Services\Inventario\Despachos\Builders\Contracts\DispatchOrderParamsBuilderInterface;
use App\Services\Inventario\GuiasRemision\Builders\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use App\Services\Inventario\Transferencias\Builders\Contracts\SalesTransferParamsBuilderInterface;
use App\Services\Compras\OrdenesCompra\Builders\Contracts\PurchaseOrderBuilderInterface;
use App\Services\Compras\OrdenesCompra\Builders\PurchaseOrderBuilder;
use App\Services\Inventario\GuiasRemision\Builders\DeliveryNoteDispatchParamsBuilder;
use App\Services\Inventario\GuiasRemision\Builders\DeliveryNoteSalesTransferBuilder;
use App\Services\Inventario\Despachos\Builders\DispatchOrderParamsBuilder;
use App\Services\Inventario\Transferencias\Builders\SalesTransferParamsBuilder;
use App\Services\Cobranzas\CobrosFacturas\Builders\CollectionRegistrationBuilder;
use App\Services\Cobranzas\CobrosFacturas\Builders\Contracts\CollectionRegistrationBuilderInterface;

class BodegaBuilderServiceProvider extends ServiceProvider implements DeferrableProvider
{



    public array $singletons = [
        // Bodega
        DeliveryNoteParamsBuilderInterface::class            => DeliveryNoteParamsBuilder::class,
        ReservationDeliveryNoteParamsBuilderInterface::class => ReservationDeliveryNoteParamsBuilder::class,
        DeliveryNoteDispatchParamsBuilderInterface::class    => DeliveryNoteDispatchParamsBuilder::class,

        // Devoluciones (Este es el que usa tu Job)
        DevolucionParamsBuilderInterface::class              => DevolucionParamsBuilder::class,

        // Venta
        QuotationBuilderInterface::class                        => QuotationBuilder::class,
        OrderBuilderInterface::class                            => OrderBuilder::class,
        ReservedBuilderInterface::class                         => ReservedBuilder::class,
        ClientBuilderInterface::class                           => ClientBuilder::class,
        PurchaseOrderBuilderInterface::class                    => PurchaseOrderBuilder::class,
        DeliveryNoteSalesTransferBuilderInterface::class        => DeliveryNoteSalesTransferBuilder::class,
        SalesTransferParamsBuilderInterface::class              => SalesTransferParamsBuilder::class,
        DispatchOrderParamsBuilderInterface::class              => DispatchOrderParamsBuilder::class,


        //cobranzas
        CollectionRegistrationBuilderInterface::class           => CollectionRegistrationBuilder::class,
    ];

    /**
     * Laravel cargará este archivo solo cuando se solicite uno de estos Builders.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
