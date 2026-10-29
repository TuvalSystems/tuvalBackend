<?php

namespace App\Services\Integraciones\Api\Cobros;

use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Integraciones\Api\Endpoints\ApiGpEndpoints;

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
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_APLICACIONES_PENDIENTES, 'GET', $params);
    }


    public function getDetailedAccountStatus(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_DETALLE_ESTADO_CUENTA_CLIENTE, 'GET', $params);
    }

    public function getListCustomerPaymentApply(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_DOCUMENTOS_COBROS_APLICADOS, 'POST', $params, 'json');
    }

    public function getListCustomerCheckReceipts(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_RECIBOS_CHEQUES, 'POST', $params, 'json');
    }

    public function getListCustomerCheckPendingPayment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_CHEQUES_PENDIENTE_COBRO, 'POST', $params, 'json');
    }



    public function getPendingApplicationDocument(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_APLICACIONES_PENDIENTES_POR_DOCUMENTO, 'POST', $params, 'json');
    }

    public function getClientAccountStatement(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_ESTADO_CUENTA_CLIENTE, 'GET', $params);
    }

    public function getClientAccountStatementSummary(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_ESTADO_CUENTA_CLIENTE_RESUMEN, 'GET', $params);
    }

    public function getPendingAccountingDocument(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_DOCUMENTOS_PENDIENTE_CONTABILIZAR, 'POST', $params, 'json');
    }

    public function getValidationReleaseDispatchOrder(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COBROS_CONSULTA_LIBERACION_ORDEN_DESPACHO, 'POST', $params, 'json');
    }



    /**
     *
     *  funciones para el registro de metodos de pago en sqlServer
     *
     * */

    public function registerCashCollectionInGp(array $params)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::COBROS_FACTURAS_REGISTRAR_EFECTIVO,
            'POST',
            $params,
            'json'
        );
    }

    public function registerApplicationCollectionInGp(array $params)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::COBROS_FACTURAS_REGISTRAR_APLICACION,
            'POST',
            $params,
            'json'
        );
    }

    public function registerRetentionCollectionInGp(array $params)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::COBROS_FACTURAS_REGISTRAR_RETENCION,
            'POST',
            $params,
            'json'
        );
    }

    public function registerCheckCollectionInGp(array $params)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::COBROS_FACTURAS_REGISTRAR_CHEQUE,
            'POST',
            $params,
            'json'
        );
    }
}
