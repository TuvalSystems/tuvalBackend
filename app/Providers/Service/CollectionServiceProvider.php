<?php

namespace App\Providers\Service;

use App\Services\Cobranzas\CollectionService;
use App\Services\Cobranzas\CollectionUnapplicationService;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use App\Services\Cobranzas\CustomerPaymentApplicationService;
use App\Services\Cobranzas\Depositos\Contracts\HistoricalDepositProcessorServiceInterface;
use App\Services\Cobranzas\Depositos\HistoricalDepositProcessorService;
use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{

    public array $singletons = [
        CollectionServiceInterface::class                   => CollectionService::class,
        CustomerPaymentApplicationServiceInterface::class   => CustomerPaymentApplicationService::class,
        CollectionUnapplicationServiceInterface::class      => CollectionUnapplicationService::class,
        HistoricalDepositProcessorServiceInterface::class   => HistoricalDepositProcessorService::class

    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
