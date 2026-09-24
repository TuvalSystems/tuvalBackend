<?php
namespace App\Services\Inventario\Articulo\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ArticleServiceInterface
{
    // Define los métodos
        public function searchItemsByClasification(string $category, string $linea, string $clase): Collection;
}
