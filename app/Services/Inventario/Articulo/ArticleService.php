<?php

namespace App\Services\Inventario\Articulo;

use App\Models\Articulos\GpArticulo;
use App\Services\Inventario\Articulo\Contracts\ArticleServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class ArticleService implements ArticleServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }

    public function searchItemsByClasification(string $category, string $linea, string $clase): Collection
    {
        return GpArticulo::select([
            'id',
            'ART_CODIGO',
            'ART_DESCRIPCION'
        ])
            ->where('ART_ESTADO', 'ACTIVO')
            ->where('ART_TIPO_COMPRA', '!=', 'L')
            ->where('cod_categoria', $category)
            ->where('cod_linea', $linea)
            ->where('cod_clase', $clase)->get();
    }
}
