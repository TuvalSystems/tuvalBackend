<?php
namespace App\Services\Api\Cobros;

use App\Services\Api\ApiGpService;

class   ApiBillingService extends ApiGpService {


    public function consultCheckAvailableQuotaClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consultar_cupo_disponible_cliente'], 'GET', $params);

    }

    public function consultDocumentExpiredClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consulta_documentos_vencidos_cliente'], 'GET', $params);

    }

    public function consultDocumentWithoutSupportClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consulta_documentos_sin_soporte_cliente'], 'GET', $params);

    }

    public function consultListDocumentExpiredClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consulta_lista_documentos_vencidos_cliente'], 'GET', $params);

    }

    public function consultListDocumentWithoutSupportClient(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints['consulta_lista_documentos_sin_soporte_cliente'], 'GET', $params);

    }


    public function consultStatusAccountClient(array $params){
        return $this->httpClient->makeRequest($this->endpoints['consulta_detalle_estado_cuenta_cliente'], 'GET', $params);
    }

}
