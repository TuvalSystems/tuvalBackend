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
            'middleware' => ['api', 'auth:sanctum'], // Aquí puedes agregar los middlewares que necesites
        ]);

        require base_path('routes/channels.php');
    }
}
