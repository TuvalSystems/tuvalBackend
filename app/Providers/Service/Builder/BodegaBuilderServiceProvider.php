<?php

namespace App\Providers\Service\Builder;

use Illuminate\Support\ServiceProvider;
use App\Services\Builders\Bodega\DeliveryNoteParamsBuilder;
use App\Services\Builders\Devolucion\DevolucionParamsBuilder;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use App\Services\Builders\Venta\OrderBuilder;
use App\Services\Builders\Venta\QuotationBuilder;
use App\Services\Builders\Venta\ReservedBuilder;

class BodegaBuilderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            DeliveryNoteParamsBuilderInterface::class,
            DeliveryNoteParamsBuilder::class
        );

        $this->app->singleton(
            DevolucionParamsBuilderInterface::class,
            DevolucionParamsBuilder::class
        );

        /** venta */
        $this->app->singleton(
            QuotationBuilderInterface::class,
            QuotationBuilder::class
        );


        $this->app->singleton(
            OrderBuilderInterface::class,
            OrderBuilder::class
        );

        $this->app->singleton(ReservedBuilderInterface::class, ReservedBuilder::class);
    }



    public function boot() {}
}
