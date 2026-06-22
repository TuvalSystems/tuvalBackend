<?php

namespace App\Services\Inventario\Articulo;

use App\Models\Articulos\ConfiguracionNivelesArticulo;
use App\Models\Articulos\GpArticulo;
use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use App\Traits\CleansPayloadData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleSyncService implements ArticleSyncServiceInterface
{
    use CleansPayloadData;
    public function __construct(protected readonly ApiGestionArticleService $api)
    {
        // Inyecta dependencias
    }


    public function getArticlesWithoutUpdateQuery(string $fechaIni, string $fechaFin): Builder
    {
        return GpArticulo::query()
            ->whereBetween('created_at', [
                $fechaIni,
                $fechaFin
            ])
            ->where('import_job', 0)->orderBy('ART_CODIGO');
    }


    public function markArticleInProgress(array $ids)
    {
        return GpArticulo::whereIn('id', $ids)
            ->where('import_job', 0)
            ->update([
                'import_job' => 3
            ]);
    }


    public function updateArticlesFromSqlServer(Collection $articles)
    {
        $articlesCode = $articles->pluck('ART_CODIGO')->filter()->unique()->values()->toArray();
        if (empty($articlesCode)) {
            return;
        }
        $request = ['codigosArticulos' => $articlesCode];
        Log::info('Informacion de Job', [
            $request
        ]);

        $articles->each(function ($item) {
            $artCode = trim($item->ART_CODIGO);
            if ($artCode === '') {
                return;
            }
            $datosApi = $this->getArticlePurchaseClassificationFromGp($artCode);

            if (empty($datosApi)) {
                Log::warning('No se encontró clasificación de compra para el artículo', [
                    'articulo' => $artCode,
                ]);
                $item->update([
                    'import_job' => 2,
                ]);
                return;
            }

            $item->update([
                'cod_categoria' => $this->cleanNullable($datosApi, 'categoriaCompra'),
                'cod_linea'     => $this->cleanNullable($datosApi, 'lineaCompra'),
                'cod_clase'     => $this->cleanNullable($datosApi, 'claseCompra'),
                'ART_MARCA'     => $this->cleanNullable($datosApi, 'marca'),
                'import_job'    => 1
            ]);
        });
    }


    protected function getArticlePurchaseClassificationFromGp(string $artCode): array
    {
        $resp = $this->api->getArticlePurchaseClassification([
            'item' => $artCode
        ]);
        $data = data_get($resp, 'data');
        return is_array($data) ? $data : [];
    }


    /** funcioon para obtener la configuracion de colores  */
    public function getConfigColorsWithSegment() : Collection
    {
        return ConfiguracionNivelesArticulo::activo()->get();
    }


    /** proceso para la actualizacion o creacion de un articulo  (api) */
    public function createOrUpdateArticle(array $payload): GpArticulo
    {
        $codigo = $this->clean($payload, 'codigo');
        if (!$codigo) {
            throw new \DomainException('El código del artículo es obligatorio.');
        }
        return DB::transaction(function () use ($payload, $codigo) {
            return GpArticulo::updateOrCreate(
                ['ART_CODIGO' => $codigo],
                [
                    'ART_DESCRIPCION'                   => $this->clean($payload, 'descripcion'),
                    'ART_CODIGO_ZAFIRO'                 => $this->clean($payload, 'codigoZafiro'),
                    'ART_CODIGO_TRES_LETRAS'            => $this->clean($payload, 'codigoTresLetras'),
                    'ART_CLASE_ARTICULO'                => $this->clean($payload, 'claseArticulo'),
                    'ART_METODO_VALUACION'              => $this->clean($payload, 'metodoValuacion'),
                    'ART_TIPO_COMPRA'                   => $this->clean($payload, 'tipoCompra'),
                    'ART_TIPO_PRODUCTO'                   => $this->clean($payload, 'tipoProducto'),
                    'ART_FECHA_MODIFICACION'            => $this->clean($payload, 'fechaModificacion'),
                    'ART_FECHA_CREACION'                => $this->clean($payload, 'fechaCreacion'),
                    'ART_ESTADO'                        => $this->clean($payload, 'estado'),
                    'ART_IMP_VTA'                       => $this->cleanFloat($payload, 'impuestoVenta'),
                    'ART_DECIMAL_CANTIDAD'              => $this->cleanInt($payload, 'decimalesCantidad'),
                    'ART_DECIMAL_MONEDA'                => $this->cleanInt($payload, 'decimalesMoneda'),
                    'ART_COSTO_PROMEDIO'                => $this->cleanFloat($payload, 'costoPromedio', 0),
                    'ART_PLAN_UMEDIDA'                  => $this->clean($payload, 'unidadMedida'),
                    'ART_IMP_COMPRA'                    => $this->cleanFloat($payload, 'impuestoCompra', 0),
                    'ART_SUSTITUTO_1'                   => $this->cleanNullable($payload, 'sustituto1'),
                    'ART_SUSTITUTO_2'                   => $this->cleanNullable($payload, 'sustituto2'),
                    'ART_MARCA'                         => $this->clean($payload, 'marca'),
                    'ART_MATERIAL'                      => $this->cleanNullable($payload, 'material'),
                    'ART_REFERENCIA_GENERAL'            => $this->clean($payload, 'referenciaGeneral'),
                    'ART_REFERENCIA_DETALLADA'          => $this->clean($payload, 'referenciaDetallada'),
                    'ART_PESO'                          => $this->cleanFloat($payload, 'peso'),
                    'ART_TAMANO'                        => $this->clean($payload, 'tamano'),
                    'ART_CODIGO_MARCA_MATERIAL'         => $this->clean($payload, 'codigoMarcaMaterial'),
                    'ART_DESCRIPCION_MARCA_MATERIAL'    => $this->clean($payload, 'descripcionMarcaMaterial'),
                    'combinado'                         => $this->clean($payload, 'combinado'),
                    'fecha_ultima_actividad'            => $this->clean($payload, 'fechaUltimaActividad'),
                    'dias_transcurridos'                => $this->cleanNullableInt($payload, 'diasTranscurridos'),
                    'dias_restantes'                    => $this->cleanNullableInt($payload, 'diasRestantes'),
                    'dias_enfriamiento'                 => $this->cleanNullableInt($payload, 'diasEnfriamiento'),
                    'cod_categoria'                     => $this->cleanNullable($payload, 'codigoCategoria'),
                    'cod_linea'                         => $this->cleanNullable($payload, 'codigoLinea'),
                    'cod_clase'                         => $this->cleanNullable($payload, 'codigoClase'),
                    // 'nivel'                             => $this->cleanInt($payload, 'nivel', 0),
                ]
            );
        });
    }
}
