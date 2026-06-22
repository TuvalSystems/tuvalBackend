<?php

namespace App\Services\Inventario\Articulo;

use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Inventario\Articulo\Contracts\ArticleSegmentConfigServiceInterface;
use App\Traits\CleansPayloadData;

class ArticleSegmentConfigService implements ArticleSegmentConfigServiceInterface
{
    use CleansPayloadData;
    public function __construct(protected readonly ApiGestionArticleService $api) {}

    public function getSegmentClassificationConfig(array $payload): array
    {
        $params = [
            'codigoSegmento'    => $this->cleanNullableInt($payload, 'codigoSegmento'),
            'codigoCategoria'   => $this->cleanNullable($payload, 'codigoCategoria'),
            'codigoLinea'       => $this->cleanNullable($payload, 'codigoLinea')
        ];
        return $this->fetchSegmentClassificationConfigFromGp($params);
    }


    protected function fetchSegmentClassificationConfigFromGp(array $params): array
    {
        $resp = $this->api->getArticleSegmentClassificationConfig($params);
        $data = data_get($resp, 'data');
        return is_array($data) ? $data : [];
    }
}
