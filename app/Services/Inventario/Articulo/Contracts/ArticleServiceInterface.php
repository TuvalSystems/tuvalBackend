<?php

namespace App\Services\Inventario\Articulo\Contracts;

use App\Models\Articulos\GpArticulo;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ArticleServiceInterface
{
    // Define los métodos
    public function listArticles(array $filters): LengthAwarePaginator;
    public function updateLogisticService(int $id, GpUsuario $user): void;
    public function searchItemsByClasification(string $category, string $linea, string $clase): Collection;
    public function getLogisticServiceArticle(): GpArticulo;
    
}
