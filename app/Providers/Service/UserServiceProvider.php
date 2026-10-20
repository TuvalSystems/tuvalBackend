<?php

namespace App\Providers\Service;

use App\Services\Admin\AdminLogService;
use App\Services\Admin\Contracts\AdminLogServiceInterface;
use App\Services\Admin\Contracts\LocationServiceInterface;
use App\Services\Admin\Contracts\MenuServiceInterface;
use App\Services\Admin\Contracts\ProfileServiceInterface;
use App\Services\Admin\Contracts\SectionServiceInterface;
use App\Services\Admin\LocationService;
use App\Services\Admin\MenuService;
use App\Services\Admin\ProfileService;
use App\Services\Admin\SectionService;
use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
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
