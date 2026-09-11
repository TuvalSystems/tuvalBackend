<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Broadcast::routes([
            'prefix' => 'api',
            // Mantiene compatibilidad con clientes Bearer y añade cookies web.
            'middleware' => ['api', 'auth:sanctum', 'must.change.password'],
        ]);

        require base_path('routes/channels.php');
    }
}
