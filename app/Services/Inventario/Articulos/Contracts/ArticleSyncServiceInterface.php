<?php

namespace App\Services\Inventario\Articulos\Contracts;

use App\Models\Inventario\Articulos\GpArticulo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ArticleSyncServiceInterface
{
    // Define los métodos

    public function getArticlesWithoutUpdateQuery(string $fechaIni, string $fechaFin): Builder;
    public function markArticleInProgress(array $ids);
    public function updateArticlesFromSqlServer(Collection $articles);
    public function getConfigColorsWithSegment(): Collection;
    public function createOrUpdateArticle(array $payload): GpArticulo;
}
