<?php


namespace App\Services\Api\Admin;

use App\Services\Api\Core\GpHttpClient;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiSync
{
    protected $httpClient;

    public function __construct()
    {
        $config = config("services.api_gp");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
    }


    public function getSyncClients(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::SYNC_CLIENTES, 'POST', $params, 'json');
    }

    public function getSyncClientBranches(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::SYNC_CLIENTES_SUCURSALES, 'POST', $params, 'json');
    }

    public function getSyncClientCoordinates(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::SYNC_CLIENTES_COORDENADAS, 'POST', $params, 'json');
    }
}
