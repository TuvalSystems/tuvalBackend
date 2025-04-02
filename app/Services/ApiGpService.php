<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiGpService
{

    protected $baseUrl;
    protected $timeout;
    protected $endpoints;

    public function __construct($baseUrl, $timeout, $endpoints)
    {
        $config = config("services.api_gp");
        $this->baseUrl = $config["url"];
        $this->timeout =  $config["timeout"];
        $this->endpoints = $config["endpoints"];
    }

    public function validateUser(array $params)
    {
        return $this->makeRequest($this->endpoints["validar_usuario"], 'GET', $params);
    }


    public function makeRequest(string $urlKey, string $method, array $data )
    {
        try {
            if (!isset($urlKey) || empty($urlKey)) {
            throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');
            if (strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($data);
            $response = Http::timeout($this->timeout)->get($url); // GET requests do not have a data payload
            } else {
            $response = Http::timeout($this->timeout)->{$method}($url, $data); // POST, PUT, DELETE, etc.
            }
            return $response->throw()->json();
        } catch (\InvalidArgumentException $e) {
            Log::warning("Invalid API Endpoint - {$urlKey}: " . $e->getMessage(), [
                'urlKey' => $urlKey,
                'data' => $data
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("API Request Error - {$url}: " . $e->getMessage(), [
                'url' => $url ?? null,
                'data' => $data
            ]);
            return null;
        }
    }
}
