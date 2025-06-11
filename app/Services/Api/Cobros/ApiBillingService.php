<?php
namespace App\Services\Api\Cobros;

use App\Services\Api\ApiGpService;

class   ApiBillingService extends ApiGpService {


    public function consultCreditClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consultar_cupo_disponible_cliente'], 'GET', $params);

    }

}
