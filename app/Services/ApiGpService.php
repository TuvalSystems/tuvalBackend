<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiGpService
{

    protected $baseUrl;
    protected $timeout;
    protected $endpoints;

    public function __construct()
    {
        $config = config("services.api_gp");
        $this->baseUrl = $config["url"];
        $this->timeout =  $config["timeout"];
        $this->endpoints = $config["endpoints"];
    }

    protected function validateUser(array $params)
    {
        return $this->makeRequest($this->endpoints["validar_usuario"], 'GET', $params);
    }

    protected function getBranchesClientsList(array $params)
    {

        return $this->makeRequest($this->endpoints["consultar_sucursales_cliente"], 'GET', $params);
    }

    protected function getCoordinatesList(array $params = [])
    {
        return $this->makeRequest($this->endpoints["consultar_coordenadas"], 'GET', $params);
    }


    protected function getStockWarehouse(array $params = [])
    {
        return $this->makeRequest($this->endpoints["consultar_stock_bodega"], 'GET', $params);
    }


    protected function getTransferHeader(array $params = [])
    {
        return $this->makeRequest($this->endpoints["consultar_transferencia_cabecera"], 'GET', $params);
    }

    protected function getTransferDetail(array $params = [])
    {
        return $this->makeRequest($this->endpoints["consultar_transferencia_detalle"], 'GET', $params);
    }

    protected function getStockByWarehouseAndCompartment(array $params = [])
    {
        return $this->makeRequest($this->endpoints["consultar_articulo_bodega_compartimento"], 'GET', $params);
    }


    protected function makeRequest(string $urlKey, string $method, array $data)
    {
        $url = null;
        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }


            $allowedMethods = ['GET', 'POST', 'PUTH', 'PATCH', 'delete'];
            $method = strtolower($method);
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }



            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');

            if (strtoupper($method) === 'GET' && !empty($data)) {
                $url .= '?' . http_build_query($data);
                $response = Http::timeout($this->timeout)->get($url); // GET requests do not have a data payload
            } else {
                $response = Http::timeout($this->timeout)->{$method}($url, $data); // POST, PUT, DELETE, etc.
            }
            return $response->throw()->json();
        } catch (\InvalidArgumentException $e) {
            Log::warning("Invalid API Request - {$urlKey}: " . $e->getMessage(), [
                'urlKey' => $urlKey,
                'data' => $data
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("API Request Error - {$url}: " . $e->getMessage(), [
                'url' => $url,
                'data' => $data
            ]);
            return null;
        }
    }
}
