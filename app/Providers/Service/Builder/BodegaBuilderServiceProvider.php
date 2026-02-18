<?php

namespace App\Providers\Service\Builder;

use Illuminate\Support\ServiceProvider;
use App\Services\Builders\Venta\OrderBuilder;
use App\Services\Builders\Venta\ClientBuilder;
use App\Services\Builders\Venta\ReservedBuilder;
use App\Services\Builders\Venta\QuotationBuilder;
use Illuminate\Contracts\Support\DeferrableProvider;
use App\Services\Builders\Bodega\DeliveryNoteParamsBuilder;
use App\Services\Builders\Devolucion\DevolucionParamsBuilder;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Builders\Bodega\ReservationDeliveryNoteParamsBuilder;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Bodega\DeliveryNoteDispatchParamsBuilder;

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
        QuotationBuilderInterface::class                     => QuotationBuilder::class,
        OrderBuilderInterface::class                         => OrderBuilder::class,
        ReservedBuilderInterface::class                      => ReservedBuilder::class,
        ClientBuilderInterface::class                        => ClientBuilder::class
    ];

    /**
     * Laravel cargará este archivo solo cuando se solicite uno de estos Builders.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
