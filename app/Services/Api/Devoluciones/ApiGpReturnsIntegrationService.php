<?php

namespace App\Services\Api\Devoluciones;

use App\Services\Api\ApiGpService;

class ApiGpReturnsIntegrationService extends ApiGpService
{


    public function generateCreditNote($params)
    {
        return $this->httpClient->makeRequest($this->endpoints['generar_nota_credito'], 'POST', $params, 'json');
    }

    public function responseCreditNote($params)
    {
        return $this->httpClient->makeRequest($this->endpoints['respuesta_nota_credito'], 'POST', $params,);
    }

    public function generateSalesReturn($params)
    {
        return $this->httpClient->makeRequest($this->endpoints['generar_devolucion_venta'], 'POST', $params,  'json');
    }

    public function consultItemBin(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_item_compartimento"], 'GET', $params);
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
            $this->endpoints["consultar_item_compartimento"],
            'POST',
            $requests
        );
    }


    public function consultGroupEconomicByClient(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_grupo_economico_cliente"], 'GET', $params);
    }
}
