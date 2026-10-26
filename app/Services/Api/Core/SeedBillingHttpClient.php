<?php

namespace App\Services\Api\Core;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
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
        $result = null;
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
            return $result = $this->normalizeResponse($response, $successKeyword);
        } catch (\InvalidArgumentException $e) {
            return $result = [
                'success'       => false,
                'message'       => 'Error interno de petición con SeedBilling: ' . $e->getMessage(),
                'respuesta'     => 'ERROR_INTERNO',
                'data'          => [],
            ];
        } catch (\Throwable $e) {
            return $result = [
                'success'       => false,
                'message'       => 'Error interno de conexión con SeedBilling: ' . $e->getMessage(),
                'respuesta'     => 'ERROR_INTERNO',
                'data'          => [],
            ];
        } finally {
            $this->logResponse($endpoint, $method, $url, $data, $result);
        }
    }

    protected function logResponse(string $endpoint, string $method, ?string $url, array $data, ?array $result): void
    {
        // No almacenar el token que se agrega al payload HTTP.
        unset($data['token']);
        SyncLog::logErrorSync(
            module: 'seedbilling',
            action: $endpoint,
            method: $method,
            message: $result['message'] ?? 'Sin respuesta de Seedbilling',
            externalId: $data['claveAcceso'] ?? null,
            payload: ['url' => $url, 'request' => $data, 'response' => $result],
            attempts: 1,
            status: !empty($result['success']) ? StatusSync::SUCCESS : StatusSync::FAILED,
        );
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
