<?php


namespace App\Services;

class ApiSalesService extends ApiGpService
{
    protected $baseUrl;
    protected $timeout;
    protected $endpoints;

    public function __construct()
    {
        $config = config("services.api_sales");
        $this->baseUrl = $config["url"];
        $this->timeout =  $config["timeout"];
        $this->endpoints = $config["endpoints"];
    }


    protected function getDocumentAccesKey(array $params)
    {
        return $this->makeRequest($this->endpoints["obtener_clave_acceso"], 'GET', $params);
    }
}
