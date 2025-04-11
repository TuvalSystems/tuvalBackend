<?php



namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiSeedBellingService
{

    protected $baseUrl;
    protected $timeout;
    protected $endpoints;
    protected $token;
    protected $idSuscriptor;
    public function __construct()
    {
        $config = config("services.api_seedbilling");
        $this->baseUrl      = $config["url"];
        $this->timeout      = $config["timeout"];
        $this->endpoints    = $config["endpoints"];
        $this->token        = $config["token"];
        $this->idSuscriptor = $config["idSuscriptor"];
    }


    public function anularComprobante(array $params)
    {
        return $this->makeRequest($this->endpoints["anular_comprobante"], 'POST', $params);
    }



    protected function makeRequest(string $urlKey, string $method, array $data)
    {

        $url = null;
        try {
            if (empty($urlKey)) {
                throw new \InvalidArgumentException("Endpoint '{$urlKey}' not defined in configuration.");
            }


            $allowedMethods = ['GET', 'POST', 'PUTH', 'PATCH', 'delete'];
            // $method = strtolower($method);
            if (!in_array($method, $allowedMethods)) {
                throw new \InvalidArgumentException("HTTP method '{$method}' is not allowed.");
            }

            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($urlKey, '/');

            $data['token'] = $this->token;
            $data['idSuscriptor'] = $this->idSuscriptor;

            if (strtoupper($method) === 'GET' && !empty($data)) {
                $url .= '?' . http_build_query($data);
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url); // GET requests do not have a data payload
            } else {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->{$method}($url, $data); // POST, PUT, DELETE, etc.
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
