<?php

namespace App\Providers\Service;

use App\Services\Admin\Logs\AdminLogService;
use App\Services\Admin\Logs\Contracts\AdminLogServiceInterface;
use App\Services\Admin\Ubicaciones\Contracts\LocationServiceInterface;
use App\Services\Admin\Menu\Contracts\MenuServiceInterface;
use App\Services\Admin\Perfiles\Contracts\ProfileServiceInterface;
use App\Services\Admin\Secciones\Contracts\SectionServiceInterface;
use App\Services\Admin\Ubicaciones\LocationService;
use App\Services\Admin\Menu\MenuService;
use App\Services\Admin\Perfiles\ProfileService;
use App\Services\Admin\Secciones\SectionService;
use App\Services\Admin\Usuarios\Contracts\UserServiceInterface;
use App\Services\Admin\Usuarios\UserService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public array $singletons = [
        UserServiceInterface::class => UserService::class,


        //aqui va lo de sistema
        ProfileServiceInterface::class => ProfileService::class,
        SectionServiceInterface::class => SectionService::class,
        MenuServiceInterface::class => MenuService::class,
        AdminLogServiceInterface::class => AdminLogService::class,
        LocationServiceInterface::class => LocationService::class

    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
