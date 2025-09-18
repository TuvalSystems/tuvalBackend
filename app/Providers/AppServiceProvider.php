<?php

namespace App\Providers;

use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

        /** para limpiar conexiones a bd en sleep  */
        Queue::after(fn(JobProcessed $e) => DB::disconnect());
        Queue::exceptionOccurred(fn(JobProcessed $e) => DB::disconnect());
       Queue::looping(fn () => rescue(fn () => DB::reconnect(), report: false));

    }
}
