<?php

namespace App\Services\Api\Devoluciones;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiGpReturnsIntegrationService extends ApiGpService
{


    public function generateCreditNote($params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::DEVOLUCIONES_GENERAR_NOTA_CREDITO, 'POST', $params, 'json');
    }

    public function responseCreditNote($params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::DEVOLUCIONES_RESPUESTA_NOTA_CREDITO, 'POST', $params,);
    }

    public function generateSalesReturn($params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::DEVOLUCIONES_GENERAR_DEVOLUCION_VENTA, 'POST', $params,  'json');
    }

    public function consultItemBin(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::INVENTARIO_CONSULTAR_ITEM_COMPARTIMENTO, 'GET', $params);
    }

    // test - probando en paralelo con consultMultipleItemsBin
    public function consultMultipleItemsBin(array $items, string $bodega)
    {
        // 🔄 Generar los parámetros para cada item
        $requests = collect($items)->map(function ($item) use ($bodega) {
            return [
                'bodega' => $bodega,
                'item' => $item
            ];
        })->toArray();

        // 🔄 Hacer las llamadas en paralelo usando makeAsyncRequest
        return $this->httpClient->makeAsyncRequest(
            ApiGpEndpoints::INVENTARIO_CONSULTAR_ITEM_COMPARTIMENTO,
            'GET',
            $requests
        );
    }


    public function consultGroupEconomicByClient(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::DEVOLUCIONES_CONSULTAR_GRUPO_ECONOMICO_CLIENTE, 'GET', $params);
    }
}
