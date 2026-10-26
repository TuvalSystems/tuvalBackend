<?php


namespace App\Services\Integraciones\Api;

use App\Services\Integraciones\Api\Core\GpHttpClient;
use App\Services\Integraciones\Api\Endpoints\ApiSalesEndpoints;

class ApiSalesService
{
    protected $httpClient;

    public function __construct()
    {
        $config = config("services.api_sales");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
    }

    public function getCheckoutPaymentInvoiceGp(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::RETURN_VALIDAR_PAGO_FACTURA, 'GET', $params);
    }


    /** nuevas apis de anulacion mejoradas */

    public function retrieveDocumentAccessKey(array $params)
    {

        return $this->httpClient->makeRequest(ApiSalesEndpoints::ANULACION_CLAVE_ACCESO, 'GET', $params);
    }

    public function retrieveGpJournalEntry(array $params)
    {

        return $this->httpClient->makeRequest(ApiSalesEndpoints::ANULACION_PROCESO_GP, 'POST', $params, 'json');
    }

     public function markGpInvoiceAsVoided(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::ANULACION_ACTUALIZAR_FACTURA, 'GET', $params);
    }


    public function fetchGpInvoiceInfo(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::ANULACION_INFORMACION_FACTURA, 'GET', $params);
    }

    public function validateGpInvoicePayments(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::ANULACION_VALIDAR_PAGO, 'GET', $params);
    }

}
