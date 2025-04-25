<?php

namespace App\Services\Api\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GpHttpClient
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(string $baseUrl, int $timeout)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
    }

    public function makeRequest(string $urlKey, string $method, array $data , $format = 'form')
    {
        $url = null;
        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }
            $allowedMethods = ['GET', 'POST', 'PUTH', 'PATCH', 'delete'];
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');

            if (strtoupper($method) === 'GET' && !empty($data)) {
                $url .= '?' . http_build_query($data);
                $response = Http::timeout($this->timeout)->get($url); // GET requests do not have a data payload
            } else {
                $client = Http::timeout($this->timeout);
                if ($format === 'json') {
                    $client = $client->asJson();
                } else {
                    $client = $client->asForm();
                }
                $response = $client->{$method}($url, $data);
                // $response = Http::timeout($this->timeout)->asForm()->{$method}($url, $data); // POST, PUT, DELETE, etc.
            }
            if ($response->successful()) {
                return $response->json(); // 2xx
            }
            return $response->json(); // +4xx
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
