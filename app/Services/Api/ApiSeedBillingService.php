<?php



namespace App\Services\Api;

use App\Services\Api\Core\SeedBillingHttpClient;
use App\Services\Api\Endpoints\ApiSeedBillingEndpoints;

class ApiSeedBillingService
{
    private SeedBillingHttpClient $cancelClient;
    private SeedBillingHttpClient $consultClient;

    public function __construct()
    {
        $config = config("services.api_seedbilling");

        $this->cancelClient = new SeedBillingHttpClient(
            $config['url_cancel'],
            $config['timeout'],
            $config['token'],
            $config['idSuscriptor']
        );

        $this->consultClient  = new SeedBillingHttpClient(
            $config['url_consult'],
            $config['timeout'],
            $config['token'],
            $config['idSuscriptor']
        );
    }


    public function cancelDocument(array $params): array
    {
        return $this->cancelClient->makeRequest(ApiSeedBillingEndpoints::SEEDBILLING_CANCEL_DOCUMENT, 'POST', $params,  successKeyword:'COMPROBANTE ENVIADO PARA SER ANULADO');
    }

    public function checkDocumentCancellationStatus(array $params): array
    {
        return $this->consultClient->makeRequest(ApiSeedBillingEndpoints::SEEDBILLING_CHECK_DOCUMENT_STATUS, 'GET', $params,   includeCredentials: false);
    }
}
