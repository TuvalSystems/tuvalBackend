<?php

namespace App\Providers\Service;

use App\Services\Integraciones\Sincronizacion\Contracts\SyncClientsServiceInterface;
use App\Services\Integraciones\Sincronizacion\SyncClientsService;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    public array $singletons = [
     SyncClientsServiceInterface::class => SyncClientsService::class
    ];


    public function provides(){
        return array_keys($this->singletons);
    }
}
