<?php

namespace App\Services\Integraciones\Api\Transacciones;


use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Integraciones\Api\Endpoints\ApiGpEndpoints;

class ApiGpProcessInvoiceVta extends ApiGpService
{

    public function storeDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_GUARDAR_FACTURA_VTA, 'POST', $params, 'json');
    }


    public function responseDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_RESPUESTA_FACTURA_VTA, 'POST', $params, 'json');
    }

    public function deleteDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::TRANSACCIONES_ELIMINAR_FACTURA_VTA, 'POST', $params, 'json');
    }
}
