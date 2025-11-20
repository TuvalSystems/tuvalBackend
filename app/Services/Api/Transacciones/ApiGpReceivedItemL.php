<?php

namespace App\Services\Api\Transacciones;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

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
}
