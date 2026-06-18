<?php

namespace App\Models\Articulos;

use App\Models\GpCotizacionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * App\Models\GpArticulo
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpCotizacionDetalle> $detallecotizacionArticulo
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
        'import_job'
    ];

    public static function busqueda($buscar): Collection
    {
        $resultados = self::join('gp_unidad_medidas', 'gp_unidad_medidas.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_CLASE_ARTICULO', '!=', 'BNS-INACT')
            ->where('gp_articulos.ART_CLASE_ARTICULO', '!=', 'CMP-GASTOS')
            ->where('gp_articulos.ART_TIPO_COMPRA', '!=', 'L');
        if (isset($buscar['codigo3l']) && $buscar['codigo3l'] != '') {
            $resultados = $resultados->where('gp_articulos.ART_CODIGO_TRES_LETRAS', $buscar['codigo3l']);
        }
        if (isset($buscar['text_codigo']) && $buscar['text_codigo'] != '') {
            if ($buscar['select_codigo'] == 'empieza') {
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', $buscar['text_codigo'] . '%');
            }
            if ($buscar['select_codigo'] == 'contiene') {
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%' . $buscar['text_codigo'] . '%');
            }
            if ($buscar['select_codigo'] == 'termina') {
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%' . $buscar['text_codigo']);
            }
        }
        if (isset($buscar['text_nombre']) && $buscar['text_nombre'] != '') {
            if ($buscar['select_nombre'] == 'empieza') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', $buscar['text_nombre'] . '%');
            }
            if ($buscar['select_nombre'] == 'contiene') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%' . $buscar['text_nombre'] . '%');
            }
            if ($buscar['select_nombre'] == 'termina') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%' . $buscar['text_nombre']);
            }
        }
        if (isset($buscar['terminal']) && $buscar['terminal'] != '') {
            $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%' . $buscar['terminal']);
        }
        $resultados->orderby('gp_articulos.ART_CODIGO_ZAFIRO', 'ASC')
            ->limit(500);
        //        print_r($resultados->toSql());
//        die();
        $resultados = $resultados->get(['gp_articulos.*', 'gp_unidad_medidas.UMEDIDA_BASE AS ART_PLAN_UMEDIDA']);
        return $resultados;
    }

    public static function busquedaitemsl($buscar): Collection
    {
        $resultados = self::join('gp_unidad_medidas', 'gp_unidad_medidas.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_CLASE_ARTICULO', '!=', 'BNS-INACT')
            ->where('gp_articulos.ART_CLASE_ARTICULO', '!=', 'CMP-GASTOS')
            ->where('gp_articulos.ART_TIPO_COMPRA', 'L')
            ->where('gp_articulos.disponible', '=', 1);
        if (isset($buscar['text_nombre']) && $buscar['text_nombre'] != '') {
            if ($buscar['select_nombre'] == 'empieza') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', $buscar['text_nombre'] . '%');
            }
            if ($buscar['select_nombre'] == 'contiene') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%' . $buscar['text_nombre'] . '%');
            }
            if ($buscar['select_nombre'] == 'termina') {
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%' . $buscar['text_nombre']);
            }
        }
        $resultados = $resultados
            ->inRandomOrder()->limit(500)
            ->get(['gp_articulos.*', 'gp_unidad_medidas.UMEDIDA_BASE AS ART_PLAN_UMEDIDA']);
        return $resultados;
    }


    /** nuevo proceso para consulta de items L e items de busqueda normal */


    /**
     * Summary of searchItemLModel
     * @param mixed $filters
     * @return \Illuminate\Support\Collection
     */
    public static function searchItemLModel(array $filters): Collection
    {
        $query = self::query()
            ->select([
                'gp_articulos.*',
                'unm.UMEDIDA_BASE AS ART_PLAN_UMEDIDA'
            ])
            ->join('gp_unidad_medidas as unm', 'unm.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_TIPO_COMPRA', 'L')
            ->where('gp_articulos.disponible', 1)
            ->whereNotIn('gp_articulos.ART_CLASE_ARTICULO', ['BNS-INACT', 'CMP-GASTOS']);

        $textSearch = $filters['text_nombre'] ?? null;
        if (!empty($textSearch)) {
            $modo = $filters['select_nombre'] ?? 'contiene';

            $patron = match ($modo) {
                'empieza' => "$textSearch%",
                'termina' => "%$textSearch",
                default => "%$textSearch%"
            };

            $query->where('gp_articulos.ART_DESCRIPCION', 'like', $patron);
        }
        return $query->orderBy('gp_articulos.ART_DESCRIPCION', 'asc')->limit(500)->get();
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
