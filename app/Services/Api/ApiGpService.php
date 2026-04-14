<?php

namespace App\Services\Api;

use App\Services\Api\Core\GpHttpClient;
use App\Services\Api\Endpoints\ApiGpEndpoints;

class ApiGpService
{

    protected $httpClient;

    protected $endpoints;

    public function __construct()
    {
        $config = config("services.api_gp");
        $this->httpClient = new GpHttpClient($config['url'], $config['timeout']);
    }

    public function validateUser(array $params)
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_VALIDAR_USUARIO, 'POST', $params, 'json');
    }

    public function getTransferHeader(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTA_TRANSFERENCIA_CABECERA, 'POST', $params, 'json');
    }

    public function getTransferDetail(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTAR_TRANSFERENCIA_DETALLE, 'POST', $params, 'json');
    }

    public function getBranchesClientsList(array $params)
    {

        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTAR_SUCURSALES_CLIENTE, 'GET', $params);
    }

    public function getCoordinatesList(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTAR_COORDENADAS, 'GET', $params);
    }


    public function getStockWarehouse(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::INVENTARIO_CONSULTAR_STOCK_BODEGA, 'POST', $params, 'json');
    }




    public function getStockByWarehouseAndCompartment(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::INVENTARIO_CONSULTAR_ARTICULO_COMPARTIMENTO, 'GET', $params);
    }

    public function transferCompartment(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::INVENTARIO_TRANSFERENCIA_COMPARTIMENTO, 'POST', $params , 'json');
    }


    public function storeQuoteReminderGp(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COTIZACIONES_GUARDAR_RECORDATORIO, 'POST', $params, 'json');
    }



    public function getListQuotationItemsAlternatives(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::COTIZACIONES_LISTA_ITEMS_ALTERNATIVOS, 'GET', $params);
    }



    /** getStockWarehouse Asyncrono aplicando llamadas en paralelismo  */

    public function getStockWarehouseAsync(array $params = [])
    {
        $request = collect($params)->map(function ($item) {
            return [
                'articulo' => $item['articulo'],
                'bodega' => $item['bodega']
            ];
        })->toArray();

        return $this->httpClient->makeBatchRequest(ApiGpEndpoints::INVENTARIO_CONSULTAR_STOCK_BODEGA_ASYNC, $request);
    }


    public function getStockExposicionByArticleAndPv(array $params = []){
        return $this->httpClient->makeRequest(ApiGpEndpoints::INVENTARIO_CONSULTA_ITEM_EXPOSICION, 'POST', $params, 'json');
    }



    public function getTypeBySeller(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_OBTENER_TIPO_VENDEDOR, 'POST', $params, 'json');
    }


    public function fetchAllSegments(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_OBTENER_TODOS_SEGMENTOS, 'GET', $params);
    }

    public function fetchSubSegmentsBySegment(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_OBTENER_SUBSEGMENTOS_SEGMENTO, 'GET', $params);
    }

    public function fetchClientSegment(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_OBTENER_SEGMENTO, 'GET', $params);
    }

    public function storeClientGp(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_GUARDAR_MODIFICAR, 'POST', $params, 'json');
    }

    public function storeSubsegmentAssociation(array $params = []){
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_GUARDAR_SUBSEGMENTO_RUC, 'POST', $params, 'json');
    }



    public function fetchReservedNotifyLocal(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::RESERVAS_OBTENER_NOTIFICACIONES_LOCAL, 'GET', $params);
    }



    public function fetchItemPriceListFromGp(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTAR_LISTA_PRECIO_ARTICULO, 'GET', $params);
    }


    public function fetchDetailsTypeSellerFromGp(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_OBTENER_TIPO_VENDEDOR_DETALLE, 'GET', $params);
    }


    public function storeNewAddressClient(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::CLIENTE_GUARDAR_NUEVA_DIRECCION, 'POST', $params, 'json');
    }


     public function fetchSegmentsAbcFromGp(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::GENERAL_CONSULTA_LISTA_SEGMENTACION_ARTICULO_ABC, 'POST', $params, 'json');
    }














    /**
     * actualizacion masiva
     */

    public function fetchAllOrdersPendingSql(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::UPDATE_BATCH_GET_ORDERS_HEADER, 'GET', $params);
    }


    public function fetchAllordersDetailPendingSql(array $params = [])
    {
        return $this->httpClient->makeRequest(ApiGpEndpoints::UPDATE_BATCH_GET_ORDERS_DETAIL, 'POST', $params, 'json');
    }


    public function updateAllOrdersPendingSql(array $params = []){
        return $this->httpClient->makeRequest(ApiGpEndpoints::UPDATE_BATCH_UPDATE_ORDERS, 'POST', $params, 'json');
    }

    public function updateAllOrdersDetailPendingSql(array $params = []){
        return $this->httpClient->makeRequest(ApiGpEndpoints::UPDATE_BATCH_UPDATE_ORDERS_DETAIL, 'POST', $params, 'json');
    }

}
