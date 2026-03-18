<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register() {}



    public function boot()
    {
        if (!method_exists(Request::class, 'host')) {
            Request::macro('host', function () {
                return request()->getHttpHost();
            });
        }
    }
}
