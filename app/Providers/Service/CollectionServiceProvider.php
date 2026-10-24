<?php

namespace App\Providers\Service;

use App\Services\Cobranzas\CierreCajas\CashRegisterClosureService;
use App\Services\Cobranzas\CierreCajas\Contracts\CashRegisterClosureServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\CollectionGpSenderService;
use App\Services\Cobranzas\CobrosFacturas\CollectionRegistrationService;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use App\Services\Cobranzas\CobrosFacturas\InvoiceCollectionService;
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
        HistoricalDepositProcessorServiceInterface::class   => HistoricalDepositProcessorService::class,


        //cobros facturas
        InvoiceCollectionServiceInterface::class            => InvoiceCollectionService::class,
        CollectionRegistrationServiceInterface::class       => CollectionRegistrationService::class,
        CollectionGpSenderServiceInterface::class           => CollectionGpSenderService::class,
        CashRegisterClosureServiceInterface::class          => CashRegisterClosureService::class
    ];

    public function provides()
    {
        return array_keys($this->singletons);
    }
}
