<?php
namespace App\Services\Inventario\Articulo\Contracts;

use App\Models\Articulos\GpArticulo;
use Illuminate\Database\Eloquent\Collection;

interface ArticleSyncServiceInterface
{
    // Define los métodos

    public function getArticlesWithoutUpdate(string $fechaIni, string $fechaFin) : Collection;
    public function markArticleInProgress(array $ids);
    public function updateArticlesFromSqlServer(Collection $articles);
      public function createOrUpdateArticle(array $payload): GpArticulo;
}
