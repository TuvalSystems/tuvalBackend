<?php

namespace App\Providers\Service;

use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Admin\Sync\SyncClientsService;
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
