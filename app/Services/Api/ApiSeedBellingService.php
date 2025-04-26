<?php



namespace App\Services\Api;

use App\Services\Api\Core\SeedBellingHttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiSeedBellingService
{
    protected  $httpClient;
    protected $endpoints;

    public function __construct( )
    {
        $config = config("services.api_seedbilling");
        $this->httpClient = new SeedBellingHttpClient($config['url'], $config['timeout'] , $config['token'], $config['idSuscriptor'], );
        $this->endpoints    = $config["endpoints"];
    }


    public function anularComprobante(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["anular_comprobante"], 'POST', $params);
    }

}
