<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiGestionArticleService extends ApiGpService
{

    public function getTrampasVaporItems(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::ARTICULOS_CONSULTAR_LISTA_TRAMPAS_VAPOR, 'POST', $params, 'json');
    }

    public function getArticlePurchaseClassification(array $params){
        return $this->httpClient->makeRequest(ApiGpEndpoints::ARTICULOS_OBTENER_CLASIFICACION, 'GET', $params);
    }

    public function getArticleSegmentClassificationConfig(array $params){
        return $this->httpClient->makeRequest(ApiGpEndpoints::ARTICULOS_OBTENER_CONFIGURACION_SEGMENTO, 'POST', $params, 'json');
    }
}
