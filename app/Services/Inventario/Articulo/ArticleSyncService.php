<?php

namespace App\Services\Inventario\Articulo;

use App\Models\Articulos\GpArticulo;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use App\Traits\CleansPayloadData;
use Illuminate\Support\Facades\DB;

class ArticleSyncService implements ArticleSyncServiceInterface
{
    use CleansPayloadData;
    public function __construct()
    {
        // Inyecta dependencias
    }


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
                    'nivel'                             => $this->cleanInt($payload, 'nivel', 0),
                ]
            );
        });
    }
}
