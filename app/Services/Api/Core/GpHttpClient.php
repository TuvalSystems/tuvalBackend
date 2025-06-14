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
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'delete'];

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

            $response = ($method === 'GET' && !empty($data)) ? $client->get($url . '?' . http_build_query($data)) : $client->{$method}($url, $data);
            $responseData = null;
            try {
                // Intentar decodificar solo si el Content-Type es JSON
                if (str_contains(strtolower($response->header('Content-Type')), 'application/json')) {
                    $responseData = $response->json();
                } else {
                    $responseData = $response->body();
                }
            } catch (\Exception $e) {
                $responseData = $response->body();
            }
            // Log para depuración avanzada cuando la respuesta es vacía o no JSON
            if (empty($responseData)) {
                Log::error("API Response vacío o no JSON en {$url}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'data' => $data,
                ]);
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'message' => 'Respuesta vacía o formato inesperado del API',
                    'body' => $response->body(),
                ];
            }

            // Log para warnings (4xx/5xx)
            if (!$response->successful()) {
                Log::warning("API Request Warning - {$url}: " . $response->body(), [
                    'url' => $url,
                    'data' => $data,
                    'status' => $response->status()
                ]);
            }

            return $responseData;

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
        return collect($results)->map(function ($result, $key) use ($url, $method) {
            if ($result['state'] === 'fulfilled') {
                return json_decode($result['value']->getBody(), true);
            } else {
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
