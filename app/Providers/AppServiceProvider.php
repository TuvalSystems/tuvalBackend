<?php

namespace App\Providers;

use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
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
        $this->clearSleepConecctionsSQLServer();
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
