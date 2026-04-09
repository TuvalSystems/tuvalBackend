<?php

namespace App\Providers\Service;

use App\Services\Cobranzas\CollectionService;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{

    public array $singletons = [
        CollectionServiceInterface::class => CollectionService::class
    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
