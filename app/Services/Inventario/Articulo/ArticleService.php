<?php

namespace App\Services\Inventario\Articulo;

use App\Models\Articulos\GpArticulo;
use App\Models\GpUsuario;
use App\Services\Inventario\Articulo\Contracts\ArticleServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ArticleService implements ArticleServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }

    public function listArticles(array $filters): LengthAwarePaginator
    {

        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $allowedSorts = [
            'codigo'                    => 'art.ART_CODIGO',
            'codigo_zafiro'             => 'art.ART_CODIGO_ZAFIRO',
            'descripcion'               => 'art.ART_DESCRIPCION',
            'codigo3l'                  => 'art.ART_CODIGO_TRES_LETRAS',
            'estado'                    => 'art.ART_ESTADO',
            'es_servicio_logistico'     => 'art.es_servicio_logistico'
        ];
        $orderColumn = data_get($allowedSorts, $sortField, 'art.created_at');
        $query = GpArticulo::query()
            ->from('gp_articulos as art')
            ->select([
                'art.id',
                'art.ART_CODIGO as codigo',
                'art.ART_CODIGO_ZAFIRO as codigo_zafiro',
                'art.ART_DESCRIPCION as descripcion',
                'art.ART_CODIGO_TRES_LETRAS as codigo3l',
                'art.ART_ESTADO as estado',
                'art.es_servicio_logistico',
                'art.created_at',
            ]);

        if ($search) {
            $query->where('art.ART_CODIGO', 'like', "%{$search}%")
                ->orWhere('art.ART_CODIGO_ZAFIRO', 'like', "%{$search}%")
                ->orWhere('art.ART_CODIGO_TRES_LETRAS', 'like', "%{$search}%");
        }

        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
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
            ->where('es_servicio_logistico', false)
            ->where('cod_categoria', $category)
            ->where('cod_linea', $linea)
            ->where('cod_clase', $clase)->get();
    }

    public function updateLogisticService(int $id, GpUsuario $user): void
    {
        $prov                       = GpArticulo::find($id);
        $prov->es_servicio_logistico     = $prov->es_servicio_logistico === true ? false : true;
        // $prov->usuario_modifica_id  = $user->id;
        // $prov->fecha_modificacion   = now();
        $prov->save();
    }
}
