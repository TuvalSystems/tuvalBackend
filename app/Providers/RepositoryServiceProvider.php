<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Pedidos\OrderRepository;
use App\Repositories\Bodegas\PurchaseReceipRepository;
use App\Repositories\Devoluciones\DevolucionRepository;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(DevolucionRepositoryInterface::class, DevolucionRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(PurchaseReceipRepositoryInterface::class, PurchaseReceipRepository::class);
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
}
