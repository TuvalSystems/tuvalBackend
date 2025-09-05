<?php

namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;


class ApiGpService
{

    protected $httpClient;

    protected $endpoints;

    public function __construct()
    {
        $config = config("services.api_gp");
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
        return $this->httpClient->makeRequest($this->endpoints["consultar_stock_bodega"], 'POST', $params, 'json');
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



    public function getListQuotationItemsAlternatives(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["lista_items_alternativos"], 'GET', $params);
    }



    /** getStockWarehouse Asyncrono aplicando llamadas en paralelismo  */

    public function getStockWarehouseAsync(array $params = [])
    {
        $request =  collect($params)->map(function ($item) {
            return [
                'articulo' => $item['articulo'],
                'bodega' => $item['bodega']
            ];
        })->toArray();

        return $this->httpClient->makeBatchRequest($this->endpoints["consultar_stock_bodega_async"],  $request);
    }



     public function getTypeBySeller(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["obtener_tipo_vendedor"], 'POST', $params, 'json');
    }

     public function fetchClientSegment(array $params = [])
    {
        return $this->httpClient->makeRequest($this->endpoints["obtener_segmento_cliente"], 'GET', $params, );
    }
}
