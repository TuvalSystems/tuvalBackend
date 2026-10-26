<?php

namespace App\Services\Integraciones\Api\Transacciones;

use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Integraciones\Api\Endpoints\ApiGpEndpoints;

class ApiGpReceivedItemL extends ApiGpService
{

    public function validateGenerationReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_VALIDAR_RECIBO_ITEM_L, 'POST', $params, 'json');
    }


    public function deleteReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_ELIMINAR_RECIBO_ITEM_L, 'POST', $params, 'json');
    }


    public function saveReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_GUARDAR_RECIBO_ITEM_L, 'POST', $params, 'json');
    }

    public function generateReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_GENERAR_RECIBO_ITEM_L, 'POST', $params, 'json');
    }

    public function responseReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_RESPUESTA_RECIBO_ITEM_L, 'POST', $params, 'json');
    }

      public function callValidateReceivedItemLApi(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_ESTADO_RECEPCION_ITEM_L, 'POST', $params, 'json');
    }


}
