<?php

namespace App\Providers;

use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            UserServiceInterface::class,
            UserService::class
        );
    }


    public function boot()
    {


        /*** proceso para que se cree el codigo de cotización cuando se genere una cotizacion nueva para evitar doble store */
        Queue::after(fn(JobProcessed $e) => DB::disconnect());
        Queue::exceptionOccurred(fn(JobProcessed $e) => DB::disconnect());
        Queue::looping(fn() => rescue(fn() => DB::reconnect(), report: false));
        $this->generateCodeQuotation();
        $this->generateCodeOrder();
        $this->clearSleepConecctionsSQLServer();
    }


    public function generateCodeQuotation()
    {
        GpCotizacion::created(function (GpCotizacion $cotizacion) {
            $code = 'COT' . str_pad($cotizacion->id, 8, "0", STR_PAD_LEFT);
            $cotizacion->updateQuietly(['cotizacion' => $code]);
        });
    }


    public function generateCodeOrder(){
        GpPedido::created(function (GpPedido $pedido) {
            $code = 'PED' . str_pad($pedido->id, 8, "0", STR_PAD_LEFT);
            $pedido->updateQuietly(['pedido' => $code]);
        });
    }



    public function clearSleepConecctionsSQLServer()
    {
        DB::listen(function ($event) {
            static $booted  = [];   // conexiones ya inicializadas
            static $booting = [];   // guardia contra re-entrancia

            $name = $event->connectionName;
            if (!in_array($name, ['sqlsrvdyn', 'sqlsrvtuv'])) {
                return;
            }

            if (!empty($booted[$name]) || !empty($booting[$name])) {
                return; // ya hecho o estamos dentro de la inicialización
            }

            $booting[$name] = true; // <-- marcar ANTES para evitar re-entrar

            try {
                $event->connection->unprepared("
                SET LOCK_TIMEOUT 5000;
                SET XACT_ABORT ON;
            ");
                $booted[$name] = true;
            } finally {
                unset($booting[$name]);
            }
        });
    }
}
