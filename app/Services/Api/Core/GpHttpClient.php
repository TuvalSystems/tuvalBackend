<?php

namespace App\Services\Api\Core;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise\Utils;

class GpHttpClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected Client $client;

    public function __construct(string $baseUrl, int $timeout)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->client = new Client(['timeout' => $this->timeout]);
    }

    public function makeRequest(string $urlKey, string $method, array $data, $format = 'form')
    {

        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }
            $allowedMethods = ['GET', 'POST', 'PUTH', 'PATCH', 'delete'];
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            // ✅ Construcción de URL
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');

            // ✅ Configuración del cliente HTTP
            $client = Http::timeout($this->timeout);

            if ($format === 'json') {
                $client = $client->asJson();
            } else {
                $client = $client->asForm();
            }

            $response = ($method === 'GET' && !empty($data))  ? $client->get($url . '?' . http_build_query($data))  : $client->{$method}($url, $data);
            if ($response->successful()) {
                return $response->json(); // 2xx
            }
            Log::warning("API Request Warning - {$url}: " . $response->body(), [
                'url' => $url,
                'data' => $data,
                'status' => $response->status()

            ]);


            return $response->json(); // +4xx
        } catch (\InvalidArgumentException $e) {
            $errorResponse = [
                'success' => false,
                'status' => 400,
                'error' => $e->getMessage(),
                'message' => "Invalid request parameters",
                'url' => $url,
                'method' => $method
            ];

            Log::warning("Invalid API Request", $errorResponse);
            return $errorResponse;
        } catch (\Exception $e) {
            $errorResponse = [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => "Internal server error during API Request",
                'url' => $url,
                'method' => $method
            ];

            Log::error("API Request Error", $errorResponse);
            return $errorResponse;
        }
    }


    public function makeAsyncRequest(string $urlKey, array $requests, string $method = 'POST', $format = 'form')
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');
        $promises = [];

        foreach ($requests as $key => $data) {
            if (strtoupper($method) === 'GET') {
                // ✅ Si es un GET, los parámetros van en la URL
                $fullUrl = $url . '?' . http_build_query($data);
                $promises[$key] = $this->client->getAsync($fullUrl);
            } else {
                // ✅ Si es un POST, se envía en el cuerpo
                $options = $format === 'json' ? ['json' => $data] : ['form_params' => $data];
                $promises[$key] = $this->client->postAsync($url, $options);
            }
        }

        $results = Utils::settle($promises)->wait();
        // $results = Promise\settle($promises)->wait();

        // 🔄 Devolver solo las que fueron exitosas
        return collect($results)->map(function ($result, $key) use ($url, $method)  {
            if ($result['state'] === 'fulfilled') {
                return json_decode($result['value']->getBody(), true);
            }
             else {
            // 🔍 Manejo de error en Promesas
            $errorResponse = [
                'success' => false,
                'status' => $result['reason']->getCode(),
                'error' => $result['reason']->getMessage(),
                'message' => "Async API Request failed",
                'url' => $url,
                'method' => $method
            ];

            // 🚀 Log detallado del error
            Log::error("Async API Request Error", $errorResponse);

            return $errorResponse;
         }
        })->filter()->toArray();
    }
}
