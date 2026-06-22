<?php
namespace App\Services\Inventario\Articulo\Contracts;

interface ArticleSegmentConfigServiceInterface
{
    // Define los métodos
     public function getSegmentClassificationConfig(array $payload): array;
}
