<?php
namespace App\Services\Inventario\Articulo\Contracts;

use App\Models\Articulos\GpArticulo;

interface ArticleSyncServiceInterface
{
    // Define los métodos

      public function createOrUpdateArticle(array $payload): GpArticulo;
}
