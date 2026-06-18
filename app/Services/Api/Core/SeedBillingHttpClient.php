<?php

namespace App\Services\Api\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SeedBillingHttpClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected string $token;
    protected int $idSuscriptor;

    public function __construct(string $baseUrl, int $timeout, string $token, int $idSuscriptor)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->token = $token;
        $this->idSuscriptor = $idSuscriptor;
    }


    public function makeRequest(
       string $endpoint,
        string $method,
        array $data,
        ?string $successKeyword = null,
        bool $includeCredentials = true
    ) {
        $url = null;
        try {
            if (empty($endpoint)) {
                throw new \InvalidArgumentException("Endpoint '{$endpoint}' no definido en configuración.");
            }
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            // ✅ Construcción de URL
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');


            $payload = $data;

            if ($includeCredentials) {
                $payload['token'] = $this->token;
                $payload['idSuscriptor'] = $this->idSuscriptor;
            }

            $client = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson();




            $response = match ($method) {
                'GET'    => $client->get($url, $payload),
                'POST'   => $client->post($url, $payload),
                'PUT'    => $client->put($url, $payload),
                'PATCH'  => $client->patch($url, $payload),
                'DELETE' => $client->delete($url, $payload),
            };

            // ✅ Manejo de respuesta
            return $this->normalizeResponse($response, $successKeyword);
        } catch (\InvalidArgumentException $e) {
            Log::warning("Petición inválida a SeedBilling: {$e->getMessage()}", [
                'urlKey'    => $endpoint,
                'method'    => $method,
                'data'      => $data,
            ]);

            return [
                'success'       => false,
                'message'       => 'Error interno de petición con SeedBilling: ' . $e->getMessage(),
                'respuesta'     => 'ERROR_INTERNO',
                'data'          => [],
            ];
        } catch (\Exception $e) {
            Log::error("Error al realizar petición a SeedBilling: {$e->getMessage()}", [
                'url'           => $url,
                'method'        => $method ?? null,
                'data'          => $data,
            ]);

            return [
                'success'       => false,
                'message'       => 'Error interno de conexión con SeedBilling: ' . $e->getMessage(),
                'respuesta'     => 'ERROR_INTERNO',
                'data'          => [],
            ];
        }
    }


    private function normalizeResponse($response, ?string $successKeyword = null): array
    {
        $body = $response->json();

        if (!is_array($body)) {
            $body = [
                'raw_response' => $response->body(),
            ];
        }

        $apiMessage = data_get($body, 'respuesta', '');

        $isSuccess = $response->successful();

        if ($successKeyword !== null) {
            $isSuccess = $isSuccess &&
                str_contains(strtoupper($apiMessage), strtoupper($successKeyword));
        }

        return [
            'success'   => $isSuccess,
            'status'    => $response->status(),
            'message'   => $apiMessage ?: 'Sin respuesta del servidor',
            'respuesta' => $apiMessage,
            'data'      => $body,
        ];
    }
}
