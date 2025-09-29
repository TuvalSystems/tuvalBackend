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


    public function consultStatusAccountClient(array $data)
    {
        $facturas = [];
        $estadoCuenta = $this->getDataStatusClientAccount($data['CLI_RUC']);

        $facturas = $estadoCuenta['detalle'] ?? [];
        return $facturas;
    }


    public function getDataStatusClientAccount($ruc)
    {
        $params = [
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ];
        $resp = $this->apiBillingService->consultStatusAccountClient($params);
        return $resp['data'] ?? [];
    }
}
