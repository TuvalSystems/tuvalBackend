<?php

namespace App\Providers\Service\Movil;

use App\Services\Movil\AppService;
use App\Services\Movil\Contracts\AppServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppsServiceProvider extends ServiceProvider
{
   public array $singletons = [
        AppServiceInterface::class => AppService::class
    ];


    public function provides()
    {
        return array_keys($this->singletons);
    }
}
