<?php

namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;


class ApiGpService
{

    protected  $httpClient;

    protected $endpoints;

    public function __construct()
    {
        $config = config( "services.api_gp");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
        $this->endpoints = $config["endpoints"];
    }

    public function validateUser(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["validar_usuario"], 'GET', $params);
    }

    public function getBranchesClientsList(array $params)
    {

        return $this->httpClient->makeRequest($this->endpoints["consultar_sucursales_cliente"], 'GET', $params);
    }

    public function getCoordinatesList(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_coordenadas"], 'GET', $params);
    }


    public function getStockWarehouse(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_stock_bodega"], 'GET', $params);
    }


    public function getTransferHeader(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_transferencia_cabecera"], 'GET', $params);
    }

    public function getTransferDetail(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_transferencia_detalle"], 'GET', $params);
    }

    public function getStockByWarehouseAndCompartment(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["consultar_articulo_bodega_compartimento"], 'GET', $params);
    }

    public function transferCompartment(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["transferencia_compartimento"], 'GET', $params);
    }


    public function storeQuoteReminderGp(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["guardar_cotizacion_recordatorio"], 'POST', $params, 'json');

    }




}
