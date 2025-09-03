<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;



class ApiDeliverNoteService  extends ApiGpService
{

    public function createTransferGuideVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.trfv.movimientos.generar"], 'POST', $params, 'json');
    }

     public function getAccessCodeByNumberDocDelivery(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.obtener.clave_acceso"], 'GET', $params );
    }

    public function createReplacementShipmentGuideVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.reposicion.movimiento.generar"], 'POST', $params, 'json');
    }

    public function createDispatchShipmentGuideVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guia.despacho.movimiento.generar"], 'POST', $params, 'json');
    }
}
