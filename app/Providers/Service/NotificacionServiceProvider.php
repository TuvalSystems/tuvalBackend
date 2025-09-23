<?php

namespace App\Providers\Service;

use Illuminate\Support\ServiceProvider;
use App\Services\Notificacion\NotificationService;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Support\DeferrableProvider;

class NotificacionServiceProvider extends ServiceProvider
{

    public function register()
    {


        /** servicio dedicado a las notificciones */
        $this->app->singleton(NotificationServiceInterface::class, NotificationService::class);
    }


   public function boot(){}

}
