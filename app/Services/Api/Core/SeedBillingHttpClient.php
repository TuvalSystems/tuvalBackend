<?php

namespace App\Services\Api\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SeedBillingHttpClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected $token;
    protected $idSuscriptor;

    public function __construct(string $baseUrl, int $timeout, string $token, string $idSuscriptor)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->token = $token;
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

            $data['token'] = $this->token;
            $data['idSuscriptor'] = $this->idSuscriptor;

            $client = Http::timeout($this->timeout)->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);


            $response = $method === 'GET'
                ? $client->get($url, $data)
                : $client->{$method}($url, $data);

            // ✅ Manejo de respuesta
            $responseBody = $response->json();
            return $this->normalizeResponse($response, $responseBody);
        } catch (\InvalidArgumentException $e) {
            Log::warning("❌ Petición inválida a API - {$urlKey}: " . $e->getMessage(), [
                'urlKey' => $urlKey,
                'method' => $method,
                'data' => $data
            ]);
             return [
                'success' => false,
                'message' => 'Error interno de petición con SeedBilling: ' . $e->getMessage(),
                'respuesta' => 'ERROR_INTERNO',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error("💥 Error al realizar petición a API - {$url}: " . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => 'Error interno de conexión con SeedBilling: ' . $e->getMessage(),
                'respuesta' => 'ERROR_INTERNO',
                'data' => []
            ];
        }
    }


    private function normalizeResponse($response, $body): array
    {
        // 1. Mensaje que devuelve la API
        $apiMessage = data_get($body, 'respuesta', '');

        // 2. Definimos cuál es el mensaje de éxito esperado
        // NOTA: Asegúrate que este string sea exacto o usa str_contains
        $successKeyword = "COMPROBANTE ENVIADO PARA SER ANULADO";

        // 3. Determinamos si fue exitoso
        // Debe ser status 200 Y contener el mensaje de éxito
        $isSuccess = $response->successful() &&
            str_contains(strtoupper($apiMessage), $successKeyword);

        return [
            'success'   => $isSuccess, // Ahora sí devolvemos un booleano real
            'message'   => $apiMessage ?: 'Sin respuesta del servidor',
            'respuesta' => $apiMessage, // Mantenemos compatibilidad si lo usas en otro lado
            'data'      => $body
        ];
    }
}
