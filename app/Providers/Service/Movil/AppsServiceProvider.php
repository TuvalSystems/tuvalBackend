<?php

namespace App\Providers\Service\Movil;

use App\Services\Movil\AppService;
use App\Services\Movil\Contracts\AppServiceInterface;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use App\Services\Movil\Gestion\SteamTrapService;
use App\Services\Movil\Menu\Contracts\MobileMenuServiceInterface;
use App\Services\Movil\Menu\MobileMenuService;
use Illuminate\Support\ServiceProvider;

class AppsServiceProvider extends ServiceProvider
{
   public array $singletons = [
        AppServiceInterface::class => AppService::class,

        //menu
        MobileMenuServiceInterface::class => MobileMenuService::class,
        //gestion equipos
        SteamTrapServiceInterface::class => SteamTrapService::class
    ];


    public function provides()
    {
        return array_keys($this->singletons);
    }
}
