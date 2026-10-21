<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiDeliveryNotePurchaseOrderService extends ApiGpService
{
    public function createPurchaseOrder(array $payload): array
    {
        // Crear documentos no es idempotente: no repetir un POST por timeout o 5xx.
        $response = $this->httpClient->makeRequest(
            ApiGpEndpoints::ORDEN_COMPRA_CREAR_GUIA_REMISION,
            'POST',
            $payload,
            'json',
            false
        );

        return is_array($response) ? $response : [];
    }
}
