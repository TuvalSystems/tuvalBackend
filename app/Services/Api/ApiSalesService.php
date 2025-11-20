<?php


namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;
use App\Services\Api\Endpoints\ApiSalesEndpoints;

class ApiSalesService
{
    protected $baseUrl;
    protected $timeout;
    protected $httpClient;

    public function __construct()
    {

        $config = config("services.api_sales");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
        $this->baseUrl = $config["url"];
        $this->timeout =  $config["timeout"];

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


}
