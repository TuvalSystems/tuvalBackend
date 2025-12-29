<?php

namespace App\Providers\Service;

use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider implements DeferrableProvider
{
   public array $singletons = [
        UserServiceInterface::class => UserService::class,
    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
