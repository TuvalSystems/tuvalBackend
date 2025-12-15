<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Pedidos\OrderRepository;
use App\Repositories\Venta\QuotationRepository;
use App\Repositories\Bodegas\PurchaseReceipRepository;
use App\Repositories\Devoluciones\DevolucionRepository;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use App\Repositories\Bodegas\Contracts\TransferRepositoryInterface;
use App\Repositories\Bodegas\TransferRepository;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Repositories\Venta\Contracts\InventoryReservationRepositoryInterface;
use App\Repositories\Venta\InventoryReservationRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DevolucionRepositoryInterface::class, DevolucionRepository::class);
        $this->app->singleton(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->singleton(PurchaseReceipRepositoryInterface::class, PurchaseReceipRepository::class);
        $this->app->singleton(QuotationRepositoryInterface::class, QuotationRepository::class);
        $this->app->singleton(TransferRepositoryInterface::class, TransferRepository::class);
        $this->app->singleton(InventoryReservationRepositoryInterface::class, InventoryReservationRepository::class);

    }



    public function boot()
    {
        //
    }
}
