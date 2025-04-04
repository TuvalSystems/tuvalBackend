<?php



namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiSeedBellingService
{

    protected $baseUrl ;
    protected $timeout ;
    protected $endpoints ;
    protected $token ;
    protected $idSuscriptor ;
    public function __construct($baseUrl, $timeout, $endpoints, $token, $idSuscriptor)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout =  $timeout;
        $this->endpoints = $endpoints;
        $this->token = $token;
        $this->idSuscriptor = $idSuscriptor;
    }


    public function anularComprobante(array $params)
    {
        return $this->makeRequest($this->endpoints["anular_comprobante"], 'POST', $params);
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
