<?php

namespace App\Providers;

use App\Models\Cobranzas\Cobros\GpCobro;
use App\Models\Devoluciones\GpDevoluciones;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Observers\Cobranzas\CobrosFacturas\GpCobrosObserver;
use App\Observers\Devoluciones\GpDevolucionesObserver;
use App\Observers\Ventas\GpCotizacionObserver;
use App\Observers\Ventas\GpOrdenDespachoObserver;
use App\Observers\Ventas\GpPedidoObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        GpPedido::observe(GpPedidoObserver::class);
        GpCotizacion::observe(GpCotizacionObserver::class);
        GpDevoluciones::observe(GpDevolucionesObserver::class);
        GpOrdenDespacho::observe(GpOrdenDespachoObserver::class);
        GpCobro::observe(GpCobrosObserver::class);
    }
}
