<?php

namespace App\Services\Inventario\Articulo;

use App\Enums\bodegas\AbcSegment;
use App\Models\Articulos\GpArticulo;
use App\Models\GpUnidadMedida;
use App\Models\GpUsuario;
use App\Services\Inventario\Articulo\Contracts\ArticleServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\File;

class ArticleService implements ArticleServiceInterface
{
    public function __construct(
        protected readonly StockWarehouseServiceInterface $stockService,

    ) {
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


    /** funcion para busqueda de items de venta */
    public function searchArticlesWithDetails(array $filters): SupportCollection
    {
        $articulos = GpArticulo::searchItemModel($filters);

        if ($articulos->isEmpty()) {
            return collect();
        }

        $articleCodes = $articulos->pluck('ART_CODIGO');
        $groupPrices = $this->getApiPriceArticle($articleCodes->implode(',') . ',');
        $groupsByAbc = $this->getApiSegmentsAbcItem($articleCodes);

        return $articulos->map(function ($art) use ($filters, $groupPrices, $groupsByAbc) {
            $art->ART_VALOR = $art->ART_VALOR ?? 0;
            $myPrice = $groupPrices->get($art->ART_CODIGO, collect());
            $nivelPrecio = data_get($filters, 'nivel_precio', null);
            if ($nivelPrecio) {
                $precioEncontrado = $myPrice->firstWhere('idLista', $nivelPrecio);
                $valor = $precioEncontrado ? data_get($precioEncontrado, 'valor', 0) : 0;
                $art->ART_VALOR = (float) round($valor, 4);
                $art->ART_VALOR_ORIGINAL = $art->ART_VALOR;
                $art->ART_DESCRIPCION_ORIGINAL = $art->ART_DESCRIPCION;
            } else {
                $art->precios = $myPrice->values();
            }
            //obtencion de abc
            $myAbc                      = $groupsByAbc->get($art->ART_CODIGO, collect());
            $classAbc                   = ($myAbc && $myAbc->isNotEmpty()) ? data_get($myAbc->first(), 'tuval', '-') : '-';
            $art->clasificacion_tuval   = $classAbc;
            $art->debe_resaltar         = $this->shouldHighlightSegment($classAbc);
            $art->proveedores           = [];
            $art->descuento             = 0;
            $art->descuento_original    = 0;
            $art->modificador           = 0;
            $art->imagenes              = $this->getImageArticle($art->ART_CODIGO_TRES_LETRAS);
            $art->art_id                = $art->id;
            return $art;
        })->filter(
            function ($articulo) use ($filters) {
                if (!empty($filters['nivel_precio'])) {
                    return $articulo->ART_VALOR >= 0;
                }
                return count($articulo->precios) > 0;
            }
        )->sortBy('ART_CODIGO_ZAFIRO')->values();
    }

    public function getApiPriceArticle(string $article)
    {
        $response = $this->stockService->fetchListPricesArticle($article);
        return  collect($response)->groupBy('item');
    }

    public function getApiSegmentsAbcItem(SupportCollection $articles)
    {
        $itemList = is_iterable($articles) ? collect($articles)->values()->toArray() : [$articles];
        $response = $this->stockService->fetchListSegmentsAbc($itemList);
        return  collect($response)->groupBy('item');
    }

    public function getImageArticle(string $codArt)
    {
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codArt}_{$i}.jpg";
            $pathAbsolute = public_path($path);
            if (File::exists($pathAbsolute)) {
                $images[] = asset($path);
            }
        }
        return $images;
    }

    public function shouldHighlightSegment(?string $clase): bool
    {
        return AbcSegment::shouldHighlight($clase);
    }



    /** funcion para busqueda de articulo L por porcentaje 5% o 15% */
    public function findAvailableTypeLArticle(
        array $excludedArticleCodes,
        float $taxPercentage
    ): ?GpArticulo {
        $article =   GpArticulo::findAvailableTypeLArticle(
            $excludedArticleCodes,
            $taxPercentage
        );
        // $article->ART_TIPO_COMPRA         = 'L';
        $unmd = GpUnidadMedida::where('UMEDIDA_PLAN', $article->ART_PLAN_UMEDIDA)->value('UMEDIDA_BASE');
        $article->setAttribute('ART_PLAN_UMEDIDA', $unmd);
        $article->ART_VALOR               = 0;
        $article->ART_VALOR_ORIGINAL      = 0;
        $article->ART_DESCRIPCION_ORIGINAL = $article->ART_DESCRIPCION;
        $article->clasificacion_tuval     = '-';
        $article->debe_resaltar           = false;
        $article->proveedores             = [];
        $article->descuento               = 0;
        $article->descuento_original      = 0;
        $article->modificador             = 0;
        $article->imagenes                = [];
        $article->art_id                  = $article->id;
        return $article;
    }


    /** busqueda de articulo de logistica para paqueteria */
    public function getLogisticServiceArticle(): GpArticulo
    {
        $article = GpArticulo::where('es_servicio_logistico', true)
            ->where('ART_ESTADO', 'ACTIVO')
            ->firstOrFail();
        $unmd = GpUnidadMedida::where('UMEDIDA_PLAN', $article->ART_PLAN_UMEDIDA)->value('UMEDIDA_BASE');
        $article->setAttribute('ART_PLAN_UMEDIDA', $unmd);
        $article->setAttribute('ART_VALOR', 0);
        $article->setAttribute('ART_VALOR_ORIGINAL', 0);
        $article->setAttribute('ART_DESCRIPCION_ORIGINAL', $article->ART_DESCRIPCION);
        $article->setAttribute('descuento', 0);
        $article->setAttribute('descuento_original', 0);
        $article->setAttribute('art_id', $article->id);
        return $article;
    }
}
