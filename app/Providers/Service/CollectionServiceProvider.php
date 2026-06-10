<?php

namespace App\Providers\Service;

use App\Services\Cobranzas\CollectionService;
use App\Services\Cobranzas\CollectionUnapplicationService;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use App\Services\Cobranzas\CustomerPaymentApplicationService;
use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{

    public array $singletons = [
        CollectionServiceInterface::class                   => CollectionService::class,
        CustomerPaymentApplicationServiceInterface::class   => CustomerPaymentApplicationService::class,
        CollectionUnapplicationServiceInterface::class      => CollectionUnapplicationService::class,

    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
