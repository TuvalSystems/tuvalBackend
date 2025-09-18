<?php

namespace App\Providers;

use App\Models\GpCotizacion;
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
    }


    public function generateCodeQuotation()
    {
        GpCotizacion::created(function (GpCotizacion $cotizacion) {
            $code = 'COT' . str_pad($cotizacion->id, 8, "0", STR_PAD_LEFT);
            $cotizacion->updateQuietly(['cotizacion' => $code]);
        });

        /** para limpiar conexiones a bd en sleep  */
    }
}
