<?php

namespace App\Services\Api\Devoluciones;

use App\Services\Api\ApiGpService;

class ApiGpReturnsIntegrationService extends ApiGpService{


    public function generateCreditNote($params){
        return $this->httpClient->makeRequest( $this->endpoints['generar_nota_credito'], 'POST', $params, 'json');
    }

    public function responseCreditNote($params){
        return $this->httpClient->makeRequest( $this->endpoints['respuesta_nota_credito'], 'POST', $params, );
    }

    public function generateSalesReturn($params){
        return $this->httpClient->makeRequest( $this->endpoints['generar_devolucion_venta'], 'POST', $params,  'json');
    }

}
