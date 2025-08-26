<?php

namespace App\Providers\Service\Builder;

use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Builders\Bodega\DeliveryNoteParamsBuilder;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class BodegaBuilderServiceProvider extends ServiceProvider implements DeferrableProvider
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
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }


    public function provides(): array
    {
        return [DeliveryNoteParamsBuilderInterface::class];
    }
}
