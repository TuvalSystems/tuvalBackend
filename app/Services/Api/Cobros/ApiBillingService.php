<?php

namespace App\Services\Api\Cobros;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class   ApiBillingService extends ApiGpService
{


    public function consultCheckAvailableQuotaClient(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTAR_CUPO_DISPONIBLE, 'GET', $params);
    }

    public function consultDocumentExpiredClient(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTAR_DOCUMENTOS_VENCIDOS, 'GET', $params);
    }

    public function consultDocumentWithoutSupportClient(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTAR_DOCUMENTOS_SIN_SOPORTE, 'GET', $params);
    }

    public function consultListDocumentExpiredClient(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTAR_LISTA_VENCIDOS, 'GET', $params);
    }

    public function consultListDocumentWithoutSupportClient(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTAR_LISTA_SIN_SOPORTE, 'GET', $params);
    }


    public function validateBusinessDate(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_DIAS_FERIADOS, 'GET', $params);
    }


    public function getUnappliedCollections(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_COBROS_SIN_APLICAR, 'GET', $params);
    }


    public function getDetailedAccountStatus(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_DETALLE_ESTADO_CUENTA_CLIENTE, 'GET', $params);
    }

    public function getListCustomerPaymentApply(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_APLICACIONES_PAGO, 'POST', $params, 'json');
    }
}
