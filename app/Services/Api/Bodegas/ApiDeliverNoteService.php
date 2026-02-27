<?php

namespace App\Services\Api\Bodegas;

use App\Services\Api\ApiGpService;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiDeliverNoteService extends ApiGpService
{

    public function createDeliveryNote(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_REMISION_CREAR, 'POST', $params, 'json');
    }

    public function checkExistenceDeliveryNote(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_REMISION_EXISTE, 'GET', $params, 'json');
    }

    public function getAccessCodeByNumberDocDelivery(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_OBTENER_CLAVE_ACCESO, 'GET', $params);
    }

    public function createTransferGuideVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_TRFV_MOVIMIENTOS_GENERAR, 'POST', $params, 'json');
    }



    public function createReplacementShipmentGuideVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_REPOSICION_MOVIMIENTO_GENERAR, 'POST', $params, 'json');
    }

    public function createDispatchShipmentGuideVta(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_DESPACHO_MOVIMIENTO_GENERAR, 'POST', $params, 'json');
    }


    public function createReservationGuideAjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_RESERVA_MOVIMIENTO_GENERAR, 'POST', $params, 'json');
    }


    public function checkDispatchGuideAdjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_DESPACHO_CHECK_AJUSTE, 'GET', $params, 'json');
    }

    public function checkTransferGuideAdjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_TRANSFERENCIA_CHECK_AJUSTE, 'GET', $params, 'json');
    }

    public function checkRepositionGuideAdjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_REPOSICION_CHECK_AJUSTE, 'GET', $params, 'json');
    }


    public function checkReservationGuideAdjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_RESERVA_CHECK_AJUSTE, 'GET', $params, 'json');
    }





    /** proceso de recibir guia de reserva */

    public function checkReceiveGuideReservationAjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_RESERVA_RECIBIDO_CHECK_AJUSTE, 'GET', $params, 'json');
    }


    public function createReceiveGuideReservationAjustment(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_RESERVA_RECIBIR_ITEMS, 'POST', $params, 'json');
    }

    /**
     * proceso de recbir guia de transferencia de venta
     *
     */
    public function createReceiveGuideSalesTransfered(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_TRFV_RECIBIR_ITEMS, 'POST', $params, 'json');
    }

    public function checkReceivedGuideSalesTransfered(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_TRFV_RECIBIDO_CHECK_AJUSTE, 'POST', $params, 'json');
    }



    public function createReceiveGuideReplenishmentTransfered(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GUIA_REPOSICION_RECIBIR_ITEMS, 'POST', $params, 'json');
    }
}
