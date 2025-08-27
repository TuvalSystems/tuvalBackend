<?php

namespace App\Providers;

use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            UserServiceInterface::class,
            UserService::class
        );
    }


    public function boot()
    {
        //
    }
}
