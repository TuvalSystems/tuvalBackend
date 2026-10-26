<?php

namespace App\Services\Integraciones\Api\Core;

use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Client\RequestException;

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

    public function makeRequest(string $urlKey, string $method, array $data, $format = 'form', bool $retryRequests = true)
    {
        $url = null;
        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }
            $method = strtoupper($method);
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            // ✅ Construcción de URL
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');
            $cid = bin2hex(random_bytes(8));

            // ✅ Configuración del cliente HTTP
            $client = Http::timeout($this->timeout);
            $client = method_exists($client, 'connectTimeout')
                ? $client->connectTimeout(3)
                : $client->withOptions(['connect_timeout' => 3]);
            if ($retryRequests) {
                $client = $client->retry(3, 200, function ($exception) {
                    // Reintentar SI es error de conexión (cURL 28, DNS, Host down)
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    // Reintentar SI el servidor devuelve 5xx (Spring Boot está saturado o reiniciando)
                    if ($exception instanceof RequestException && $exception->response) {
                        if ($exception->response->status() > 500) {
                            return true;
                        }
                    }
                    // Para errores lógicos o 4xx no reintentar.
                    return false;
                });
            }
            $client = $client->withHeaders([
                'Accept' => 'application/json',
                'X-Correlation-Id' => $cid,
            ]);

            // Formato del body
            $client = ($format === 'json') ? $client->asJson() : $client->asForm();

            switch ($method) {
                case 'GET':
                    $response = $client->get($url, $data);
                    break;
                case 'POST':
                    $response = $client->post($url, $data);
                    break;
                case 'PUT':
                    $response = $client->put($url, $data);
                    break;
                case 'PATCH':
                    $response = $client->patch($url, $data);
                    break;
                case 'DELETE':
                    $response = $client->delete($url, $data);
                    break;
            }

            $status = $response->status();
            $contentType = strtolower($response->header('Content-Type') ?? '');
            $bodyStr = (string) $response->body();
            if ($status === 204) {
                $payload = [
                    'success' => false,
                    'status' => 204,
                    'message' => 'No content',
                    'data' => null,
                    'url' => $url,
                    'method' => $method,
                    'correlation_id' => $cid,
                ];
                Log::channel('apiservice')->warning("API 204 No Content", $payload);
                return $payload;
            }

            $dataParsed = null;
            if (str_contains($contentType, 'application/json')) {
                $dataParsed = $response->json();
            } else {
                // Algunos backends devuelven JSON con content-type incorrecto
                $decoded = json_decode($bodyStr, true);
                $dataParsed = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $bodyStr;
            }

            // Log para warnings (4xx/5xx)
            if (!$response->successful()) {
                Log::channel('apiservice')->warning("Response Api (4xx/5xx)", [
                    'url' => $url,
                    'method' => $method,
                    'status' => $status,
                    'correlation_id' => $cid,
                    'body' => mb_strimwidth($bodyStr, 0, 400000, '…'),
                    'payload' => $method === 'GET' ? $data : ($format === 'json' ? $data : '[form-data]'),
                ]);
                $payload = is_array($dataParsed)
                    ? $dataParsed
                    : ['message' => (is_string($dataParsed) && $dataParsed !== '') ? $dataParsed : ($response->reason() ?: 'HTTP ' . $status)];

                // Completa metadatos sin pisar campos existentes
                $payload['success'] = $payload['success'] ?? false;
                $payload['status'] = $status;
                $payload['url'] = $url;
                $payload['method'] = $method;
                $payload['correlation_id'] = $cid;

                return $payload;
            }
            // Si todo OK, retorna el JSON (o string) tal cual lo devolvió la API
            if ($dataParsed === null || $dataParsed === '' || $dataParsed === []) {
                Log::channel('apiservice')->error("API Response vacío o no parseable", [
                    'url' => $url,
                    'method' => $method,
                    'status' => $status,
                    'correlation_id' => $cid,
                    'content_type' => $contentType,
                    'body' => mb_strimwidth($bodyStr, 0, 4000, '…'),
                ]);
                return [
                    'success' => false, // o true si quieres asumir OK
                    'status' => $status ?? 500,
                    'message' => $bodyStr !== '' ? $bodyStr : 'Respuesta vacía o no parseable',
                    'data' => null,
                    'url' => $url,
                    'method' => $method,
                    'correlation_id' => $cid ?? null,
                ];
            }

            return $dataParsed;
        } catch (\InvalidArgumentException $e) {
            $errorResponse = [
                'success' => false,
                'status' => 400,
                'error' => $e->getMessage(),
                'message' => $e->getMessage(), // ← no ocultar el detalle
                'url' => $url,
                'method' => $method,
            ];
            Log::channel('apiservice')->warning("Invalid API Request", $errorResponse);
            return $errorResponse;

        } catch (ConnectionException $e) {
            // Errores de red / timeout
            $errorResponse = [
                'success' => false,
                'status' => 408,
                'error' => 'Time out',
                // 'message' => 'Connection error: ' . $e->getMessage(),
                'message' => 'El servicio externo no responde. Por favor intente más tarde.',
                'url' => $url,
                'method' => $method,
            ];

            Log::channel('apiservice')->error("API Connection Error", $errorResponse);
            return $errorResponse;

        } catch (RequestException $e) {
            // Si usas ->throw(), aquí puedes extraer el response y respetar el mensaje de la API
            $resp = $e->response;
            $status = optional($resp)->status();
            $body = optional($resp)->body() ?? '';
            $json = optional($resp)->json();

            $payload = is_array($json)
                ? $json
                : ['message' => ($body !== '' ? $body : $e->getMessage())];

            $payload['success'] = $payload['success'] ?? false;
            $payload['status'] = $status ?? 500;
            $payload['url'] = $url;
            $payload['method'] = $method;

            Log::channel('apiservice')->error("API RequestException Error", [
                'url' => $url,
                'method' => $method,
                'status' => $status,
                'message' => mb_strimwidth($body ?: $e->getMessage(), 0, 4000, '…'),
            ]);

            return $payload;

        } catch (\Exception $e) {
            // Cualquier otro error: expón el mensaje real
            $errorResponse = [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => $e->getMessage(), // ← clave: no reemplazar por un texto genérico
                'url' => $url,
                'method' => $method,
            ];
            Log::channel('apiservice')->error("API Exception Error", $errorResponse);
            return $errorResponse;
        }
    }


    public function makeAsyncRequest(string $urlKey, string $method = 'POST', array $requests, $format = 'form')
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
                Log::channel('apiservice')->error("Async API Request Error", $errorResponse);

                return $errorResponse;
            }
        })->filter()->toArray();
    }


    /** para procesos en lotes para consulta de stock para recuperar cotizaciones
     * Adicional, siempre seran en POST ya que GET no admite json por eso en este caso siempre post
     */

    public function makeBatchRequest(string $urlKey, array $data)
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');

        try {
            $response = $this->client->post($url, [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::channel('apiservice')->error('Error in makeBatchRequest', [
                'url' => $url,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [];
        }
    }
}
