<?php

namespace App\Providers;

use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Repositories\Devoluciones\DevolucionRepository;
use Illuminate\Support\ServiceProvider;

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
