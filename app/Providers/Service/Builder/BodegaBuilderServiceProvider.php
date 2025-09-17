<?php

namespace App\Providers\Service\Builder;

use Illuminate\Support\ServiceProvider;
use App\Services\Builders\Bodega\DeliveryNoteParamsBuilder;
use App\Services\Builders\Devolucion\DevolucionParamsBuilder;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Builders\Venta\QuotationBuilder;

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
    }



    public function boot()
    {
    }

}
