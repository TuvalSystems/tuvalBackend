<?php

namespace App\Models\Inventario\Articulos;

use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * App\Models\Inventario\Articulos\GpArticulo
 *
 * @property int $id
 * @property string $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_CODIGO_ZAFIRO
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property string $ART_CLASE_ARTICULO
 * @property string|null $ART_METODO_VALUACION
 * @property string $ART_TIPO_PRODUCTO
 * @property string $ART_FECHA_MODIFICACION
 * @property string $ART_FECHA_CREACION
 * @property string $ART_ESTADO
 * @property string $ART_IMP_VTA
 * @property int $ART_DECIMAL_CANTIDAD
 * @property int $ART_DECIMAL_MONEDA
 * @property string $ART_COSTO_PROMEDIO
 * @property string $ART_PLAN_UMEDIDA
 * @property string $ART_IMP_COMPRA
 * @property string|null $ART_SUSTITUTO_1
 * @property string|null $ART_SUSTITUTO_2
 * @property string|null $ART_MARCA
 * @property string|null $ART_MATERIAL
 * @property string|null $ART_REFERENCIA_GENERAL
 * @property string|null $ART_REFERENCIA_DETALLADA
 * @property string $ART_TIPO_COMPRA
 * @property string|null $ART_PESO
 * @property string|null $ART_TAMANO
 * @property string|null $ART_CODIGO_MARCA_MATERIAL
 * @property string|null $ART_DESCRIPCION_MARCA_MATERIAL
 * @property string|null $combinado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $disponible
 * @property string|null $fecha_ultima_actividad
 * @property string|null $dias_transcurridos
 * @property string|null $dias_restantes
 * @property string|null $dias_enfriamiento
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Cotizaciones\GpCotizacionDetalle> $detallecotizacionArticulo
 * @property-read int|null $detallecotizacion_articulo_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCLASEARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCODIGOMARCAMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCODIGOZAFIRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTCOSTOPROMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTDECIMALCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTDECIMALMONEDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTDESCRIPCIONMARCAMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTFECHACREACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTFECHAMODIFICACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTIMPCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTIMPVTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTMETODOVALUACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTREFERENCIADETALLADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTREFERENCIAGENERAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTSUSTITUTO1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTSUSTITUTO2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTTAMANO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereARTTIPOPRODUCTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereCombinado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereDiasEnfriamiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereDiasRestantes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereDiasTranscurridos($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereDisponible($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereFechaUltimaActividad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpArticulo extends Model
{
    use HasFactory;

    protected $fillable = [
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_CODIGO_ZAFIRO',
        'ART_CODIGO_TRES_LETRAS',
        'ART_CLASE_ARTICULO',
        'ART_METODO_VALUACION',
        'ART_TIPO_COMPRA',
        'ART_FECHA_MODIFICACION',
        'ART_FECHA_CREACION',
        'ART_ESTADO',
        'ART_IMP_VTA',
        'ART_DECIMAL_CANTIDAD',
        'ART_DECIMAL_MONEDA',
        'ART_COSTO_PROMEDIO',
        'ART_PLAN_UMEDIDA',
        'ART_IMP_COMPRA',
        'ART_SUSTITUTO_1',
        'ART_SUSTITUTO_2',
        'ART_MARCA',
        'ART_MATERIAL',
        'ART_REFERENCIA_GENERAL',
        'ART_REFERENCIA_DETALLADA',
        'ART_PESO',
        'ART_TAMANO',
        'ART_CODIGO_MARCA_MATERIAL',
        'ART_DESCRIPCION_MARCA_MATERIAL',
        'combinado',
        'disponible',
        'fecha_ultima_actividad',
        'dias_transcurridos',
        'dias_restantes',
        'dias_enfriamiento',
        'cod_categoria',
        'cod_linea',
        'cod_clase',
        'import_job',
        'ART_TIPO_PRODUCTO',
        'es_servicio_logistico'
    ];

    protected $casts = [
        'ART_PESO'             => 'decimal:2',
        'ART_IMP_VTA'           => 'float',
        'es_servicio_logistico' => 'boolean',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',

    ];
    public static function findAvailableTypeLArticle(
        array $excludedArticleCodes,
        float $taxPercentage
    ): ?self {
        return self::query()
            ->select([
                'art.*',
                'unm.UMEDIDA_BASE AS ART_PLAN_UMEDIDA'
            ])
            ->from('gp_articulos as art')
            ->join(
                'gp_unidad_medidas as unm',
                'unm.UMEDIDA_PLAN',
                '=',
                'art.ART_PLAN_UMEDIDA'
            )
            ->where('art.ART_ESTADO', 'ACTIVO')
            ->where('art.ART_TIPO_COMPRA', 'L')
            ->where('art.disponible', 1)
            ->where('art.es_servicio_logistico', false)
            ->where('art.ART_IMP_VTA', $taxPercentage)
            ->when(
                $excludedArticleCodes !== [],
                fn($query) => $query->whereNotIn('art.ART_CODIGO', $excludedArticleCodes)
            )
            ->whereNotIn('art.ART_CLASE_ARTICULO', [
                'BNS-INACT',
                'CMP-GASTOS',
                'VTA-SERV'
            ])
            ->orderBy('art.ART_DESCRIPCION')
            ->first();
    }

    public static function searchItemModel(array $filters): Collection
    {
        $query = self::query()
            ->select([
                'gp_articulos.*',
                'unm.UMEDIDA_BASE AS ART_PLAN_UMEDIDA'
            ])
            ->join('gp_unidad_medidas as unm', 'unm.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_TIPO_COMPRA', '!=', 'L') // Aquí excluimos los items 'L'
            ->where('gp_articulos.es_servicio_logistico', false) // filtro para paqueteria
            ->whereNotIn('gp_articulos.ART_CLASE_ARTICULO', ['BNS-INACT', 'CMP-GASTOS']);
        if (!empty($filters['codigo3l'])) {
            $query->where('gp_articulos.ART_CODIGO_TRES_LETRAS', $filters['codigo3l']);
        }

        $textCodigo = $filters['text_codigo'] ?? null;
        if (!empty($textCodigo)) {
            $modo = $filters['select_codigo'] ?? 'contiene';

            $patron = match ($modo) {
                'empieza' => "$textCodigo%",
                'termina' => "%$textCodigo",
                default => "%$textCodigo%",
            };

            $query->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', $patron);
        }

        $textNombre = $filters['text_nombre'] ?? null;
        if (!empty($textNombre)) {
            $modo = $filters['select_nombre'] ?? 'contiene';

            $patron = match ($modo) {
                'empieza' => "$textNombre%",
                'termina' => "%$textNombre",
                default => "%$textNombre%",
            };

            $query->where('gp_articulos.ART_DESCRIPCION', 'LIKE', $patron);
        }

        if (!empty($filters['terminal'])) {
            $query->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%' . $filters['terminal']);
        }

        return $query
            ->orderBy('gp_articulos.ART_CODIGO_ZAFIRO', 'ASC')
            ->limit(500)
            ->get();
    }


    public function detallecotizacionArticulo()
    {
        return $this->hasMany(GpCotizacionDetalle::class, 'ART_CODIGO', 'ART_CODIGO');
    }
}
