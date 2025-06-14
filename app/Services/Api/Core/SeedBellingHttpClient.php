<?php

namespace App\Services\Api\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class  SeedBellingHttpClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected $token;
    protected $idSuscriptor;

    public function __construct(string $baseUrl, int $timeout, string $token, string $idSuscriptor)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->token        = $token;
        $this->idSuscriptor = $idSuscriptor;
    }


    public function makeRequest(string $urlKey, string $method, array $data)
    {
        $url = null;
        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' no definido en configuración.");
            }
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'delete'];
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            // ✅ Construcción de URL
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');
            $client = Http::timeout($this->timeout)->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $data['token'] = $this->token;
            $data['idSuscriptor'] = $this->idSuscriptor;


            $response = $method === 'GET'
                ? $client->get($url, $data)
                : $client->{$method}($url, $data);

            // ✅ Manejo de respuesta
            if ($response->successful()) {
                return $response->json(); // 2xx
            }
            return $response->json(); // +4xx
        } catch (\InvalidArgumentException $e) {
            Log::warning("❌ Petición inválida a API - {$urlKey}: " . $e->getMessage(), [
                'urlKey' => $urlKey,
                'method' => $method,
                'data' => $data
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("💥 Error al realizar petición a API - {$url}: " . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
                'data' => $data
            ]);
            return null;
        }
    }
}
