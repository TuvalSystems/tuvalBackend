<?php

namespace App\Providers\Service;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;


class ReposicionesServiceProvider extends ServiceProvider implements DeferrableProvider
{


    public array $singletons = [
        // Interfaz => Implementación

    ];


    public function provides()
    {
        return array_keys($this->singletons);
    }
}
