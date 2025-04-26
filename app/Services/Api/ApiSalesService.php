<?php


namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;

class ApiSalesService
{
    protected $baseUrl;
    protected $timeout;
    protected $endpoints;
    protected $httpClient;

    public function __construct()
    {

        $config = config("services.api_sales");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
        $this->baseUrl = $config["url"];
        $this->timeout =  $config["timeout"];
        $this->endpoints = $config["endpoints"];
    }


    public function getDocumentAccesKey(array $params)
    {

        return $this->httpClient->makeRequest($this->endpoints["obtener_clave_acceso"], 'GET', $params);
    }

    public function invalidateGpVoucher(array $params)
    {

        return $this->httpClient->makeRequest($this->endpoints["anular_comprobante_gp"], 'POST', $params);
    }

    public function updatedStatusDocumentGp(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["actualizar_estado_factura"], 'POST', $params);
    }




}
