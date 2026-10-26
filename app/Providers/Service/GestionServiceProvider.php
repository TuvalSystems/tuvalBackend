<?php

namespace App\Providers\Service;

use App\Services\Gestion\Sucursales\ClientBranchService;
use App\Services\Gestion\Sucursales\Contracts\ClientBranchServiceInterface;
use Illuminate\Support\ServiceProvider;

class GestionServiceProvider extends ServiceProvider
{
   public array $singletons = [
        // Interfaz => Implementación
        ClientBranchServiceInterface::class => ClientBranchService::class,

    ];


    public function provides()
    {
        return array_keys($this->singletons);
    }
}
