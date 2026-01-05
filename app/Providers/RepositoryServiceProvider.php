<?php

namespace App\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
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

class RepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
   
    // public function register()
    // {
    //     $this->app->singleton(DevolucionRepositoryInterface::class, DevolucionRepository::class);
    //     $this->app->singleton(OrderRepositoryInterface::class, OrderRepository::class);
    //     $this->app->singleton(PurchaseReceipRepositoryInterface::class, PurchaseReceipRepository::class);
    //     $this->app->singleton(QuotationRepositoryInterface::class, QuotationRepository::class);
    //     $this->app->singleton(TransferRepositoryInterface::class, TransferRepository::class);
    //     $this->app->singleton(InventoryReservationRepositoryInterface::class, InventoryReservationRepository::class);

    // }



public array $singletons = [
        DevolucionRepositoryInterface::class           => DevolucionRepository::class,
        OrderRepositoryInterface::class                => OrderRepository::class,
        PurchaseReceipRepositoryInterface::class       => PurchaseReceipRepository::class,
        QuotationRepositoryInterface::class            => QuotationRepository::class,
        TransferRepositoryInterface::class             => TransferRepository::class,
        InventoryReservationRepositoryInterface::class => InventoryReservationRepository::class,
    ];

    /**
     * Indica a Laravel qué repositorios provee este archivo.
     * Si el controlador no pide ninguno de estos, este archivo ni se toca.
     */
    public function provides()
    {
        return array_keys($this->singletons);
    }
}
