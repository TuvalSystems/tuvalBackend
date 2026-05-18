<?php
namespace App\Services\Cobranzas;

use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;

class CustomerPaymentApplicationService implements CustomerPaymentApplicationServiceInterface
{

    //serivicio dedicado para la verificacion de aplicación de pagos antes de registrar un cobro
    public function __construct(protected ApiBillingService $api)
    {
    }

    
}
