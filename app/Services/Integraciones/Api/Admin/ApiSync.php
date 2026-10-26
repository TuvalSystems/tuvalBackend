<?php


namespace App\Services\Integraciones\Api\Admin;

use App\Services\Integraciones\Api\Core\GpHttpClient;
use App\Services\Integraciones\Api\Endpoints\ApiGpEndpoints;

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

    public function getClientPendingBranchConfigSyncCount(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_CONSULTA_TOTAL_CONFIGURACIONES_PENDIENTES_SYNC, 'GET', $params);
    }

    public function markBranchConfigurationSynced(array $key)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::CLIENTE_MARCAR_CONFIGURACION_SINCRONIZADA, 'POST', $key, 'json'
        );
    }

    public function saveBranchConfigurationHistory(array $key)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::CLIENTE_GUARDAR_HISTORICO_CONFIGURACION, 'POST', $key, 'json'
        );
    }

    public function confirmBranchConfigurationSync(array $key)
    {
        return $this->httpClient->makeRequest(
            ApiGpEndpoints::CLIENTE_CONFIRMAR_CONFIGURACION_SYNC, 'POST', $key, 'json'
        );
    }

    public function getPendingBranchConfigs(int $limit = 1, ?array $cursor = null)
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('El límite debe estar entre 1 y 1000.');
        }

        $params = ['limit' => $limit];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return $this->httpClient->makeRequest(
            ApiGpEndpoints::CLIENTE_CONSULTA_CONFIGURACIONES_PENDIENTES_SYNC,
            'POST',
            $params,
            'json'
        );
    }
}
