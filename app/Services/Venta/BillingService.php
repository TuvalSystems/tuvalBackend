<?php

namespace App\Services\Venta;

use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Venta\Contracts\BillingServiceInterface;

class BillingService implements BillingServiceInterface
{

    protected $apiBillingService;
    public function __construct(ApiBillingService $apiBillingService)
    {
        $this->apiBillingService = $apiBillingService;
    }


    public function consultStatusAccountClient(array $data){
        
    }
}
