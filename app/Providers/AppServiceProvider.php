<?php

namespace App\Providers;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        
    }


    public function boot()
    {

        /*** proceso para que se cree el codigo de cotización cuando se genere una cotizacion nueva para evitar doble store */
        Queue::before(function (JobProcessing $e) {
            // Ej: podrías asegurar conexión aquí si quisieras
            // DB::reconnect();  // opcional
        });

        // Después de procesar OK
        Queue::after(function (JobProcessed $e) {
            DB::disconnect(); // cierra conexiones abiertas
        });

        // Ocurrió excepción (pero puede haber más reintentos)
        Queue::exceptionOccurred(function (JobExceptionOccurred $e) {
            DB::disconnect(); // corta conexiones colgantes tras excepción
        });

        // Job fallido definitivo
        Queue::failing(function (JobFailed $e) {
            DB::disconnect();
        });

        // Si el worker entra en ciclo de loop, reconecta con rescue
        Queue::looping(fn() => rescue(fn() => DB::reconnect(), report: false));
      
    }


}
