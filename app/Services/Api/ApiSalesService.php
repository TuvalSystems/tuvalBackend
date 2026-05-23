<?php


namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;
use App\Services\Api\Endpoints\ApiSalesEndpoints;

class ApiSalesService
{
    protected $httpClient;

    public function __construct()
    {
        $config = config("services.api_sales");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
    }


    public function getDocumentAccesKey(array $params)
    {

        return $this->httpClient->makeRequest(ApiSalesEndpoints::RETURN_CLAVE_ACCESO, 'GET', $params);
    }

    public function invalidateGpVoucher(array $params)
    {

        return $this->httpClient->makeRequest(ApiSalesEndpoints::RETURN_ANULAR_COMPROBANTE_GP, 'POST', $params);
    }

    public function updatedStatusDocumentGp(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::RETURN_ACTUALIZAR_ESTADO_FACTURA, 'POST', $params);
    }

    public function getDataDocumentGp(array $params)
    {
        return $this->httpClient->makeRequest(ApiSalesEndpoints::RETURN_OBTENER_DATOS_FACTURA, 'GET', $params);
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
