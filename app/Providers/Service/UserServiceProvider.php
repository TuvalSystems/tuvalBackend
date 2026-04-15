<?php

namespace App\Providers\Service;

use App\Services\Admin\AdminLogService;
use App\Services\Admin\Contracts\AdminLogServiceInterface;
use App\Services\Admin\Contracts\MenuServiceInterface;
use App\Services\Admin\MenuService;
use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider implements DeferrableProvider
{
   public array $singletons = [
        UserServiceInterface::class => UserService::class,


        //aqui va lo de sistema
        MenuServiceInterface::class => MenuService::class,
        AdminLogServiceInterface::class => AdminLogService::class
    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
