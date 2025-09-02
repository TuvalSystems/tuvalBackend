<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;



class ApiDeliverNoteService  extends ApiGpService
{

    public function generateAndCreateGuideTrfv(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.trfv.movimientos.generar"], 'POST', $params, 'json');
    }

     public function getAccessCodeByNumberDocDelivery(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.obtener.clave_acceso"], 'GET', $params );
    }
}
