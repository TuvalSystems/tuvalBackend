<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiDeliveryNotePurchaseOrderService extends ApiGpService
{
    public function storeGuidePurchaseOrder(array $payload): array
    {
        // No repetir automáticamente un POST cuyo resultado podría ser incierto.
        $response = $this->httpClient->makeRequest(
            ApiGpEndpoints::GUIA_REMISION_ORDEN_COMPRA_GUARDAR,
            'POST',
            $payload,
            'json',
            false
        );

        if (!is_array($response) || $response === []
            || data_get($response, 'success') === false
            || (int) data_get($response, 'status', 200) >= 400) {
            throw new \RuntimeException((string) data_get(
                $response, 'message', 'SQL Server no confirmó el guardado de la relación guía/OC.'
            ));
        }

        return $response;
    }

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
