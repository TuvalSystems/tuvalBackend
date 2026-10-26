<?php
namespace App\Services\Inventario\Articulos\Contracts;

interface ArticleSegmentConfigServiceInterface
{
    // Define los métodos
     public function getSegmentClassificationConfig(array $payload): array;
}
