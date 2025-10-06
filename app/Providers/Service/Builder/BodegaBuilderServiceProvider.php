<?php

namespace App\Providers\Service\Builder;

use Illuminate\Support\ServiceProvider;
use App\Services\Builders\Bodega\DeliveryNoteParamsBuilder;
use App\Services\Builders\Devolucion\DevolucionParamsBuilder;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;

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
    }



    public function boot()
    {
    }

}
