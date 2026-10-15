<?php

namespace App\Services\Inventario\Articulo\Contracts;

use App\Models\Articulos\GpArticulo;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

interface ArticleServiceInterface
{
    // Define los métodos
    public function listArticles(array $filters): LengthAwarePaginator;
    public function updateLogisticService(int $id, GpUsuario $user): void;
    public function searchItemsByClasification(string $category, string $linea, string $clase): Collection;
    public function searchArticlesWithDetails(array $filters): SupportCollection;
    public function findAvailableTypeLArticle(
        array $excludedArticleCodes,
        float $taxPercentage
    ): ?GpArticulo;
    public function getLogisticServiceArticle(): GpArticulo;
}
