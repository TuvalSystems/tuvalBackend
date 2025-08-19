<?php

namespace App\Services\Api\Transacciones;

use App\Services\Api\ApiGpService;

class ApiGpReceivedItemL extends ApiGpService
{

    public function validateGenerationReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["validar_recibo_item_l"], 'POST', $params, 'json');
    }


    public function deleteReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["eliminar_recibo_item_l"], 'POST', $params, 'json');
    }


    public function saveReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guardar_recibo_item_l"], 'POST', $params, 'json');
    }

    public function generateReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["generar_recibo_item_l"], 'POST', $params, 'json');
    }

    public function responseReceivedItemL(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["respuesta_recibo_item_l"], 'POST', $params, 'json');
    }
}
