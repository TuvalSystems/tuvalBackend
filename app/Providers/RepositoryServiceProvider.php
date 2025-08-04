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
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Repositories\SqlServer\Venta\ClientSqlRepository;
use App\Repositories\SqlServer\Venta\Contracts\ClientSqlRepositoryInterface;

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
        $this->app->bind(QuotationRepositoryInterface::class, QuotationRepository::class);
        $this->app->bind(ClientSqlRepositoryInterface::class, ClientSqlRepository::class);
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
