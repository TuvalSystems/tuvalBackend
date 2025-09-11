<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property string|null $fecha_ultimo_uso
 * @property boolean|null $disponible
 * @property string|null $numero_dias_ultimo_uso
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticulo whereCreatedAt($value)
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
        'ART_TIPO_COMPRA',
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
    ];

    public static function busqueda($buscar): \Illuminate\Support\Collection
    {
        $resultados = self::join('gp_unidad_medidas', 'gp_unidad_medidas.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_CLASE_ARTICULO','!=',  'BNS-INACT')
            ->where('gp_articulos.ART_CLASE_ARTICULO','!=',  'CMP-GASTOS')
            ->where('gp_articulos.ART_TIPO_COMPRA','!=','L');
        if(isset($buscar['codigo3l']) && $buscar['codigo3l'] != ''){
            $resultados = $resultados->where('gp_articulos.ART_CODIGO_TRES_LETRAS', $buscar['codigo3l']);
        }
        if(isset($buscar['text_codigo']) && $buscar['text_codigo'] != ''){
            if($buscar['select_codigo'] == 'empieza'){
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', $buscar['text_codigo'].'%');
            }
            if($buscar['select_codigo'] == 'contiene'){
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%'.$buscar['text_codigo'].'%');
            }
            if($buscar['select_codigo'] == 'termina'){
                $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%'.$buscar['text_codigo']);
            }
        }
        if(isset($buscar['text_nombre']) && $buscar['text_nombre'] != ''){
            if($buscar['select_nombre'] == 'empieza'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', $buscar['text_nombre'].'%');
            }
            if($buscar['select_nombre'] == 'contiene'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%'.$buscar['text_nombre'].'%');
            }
            if($buscar['select_nombre'] == 'termina'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%'.$buscar['text_nombre']);
            }
        }
        if(isset($buscar['terminal']) && $buscar['terminal'] != ''){
            $resultados = $resultados->where('gp_articulos.ART_CODIGO_ZAFIRO', 'LIKE', '%'.$buscar['terminal']);
        }
         $resultados->orderby('gp_articulos.ART_CODIGO_ZAFIRO', 'ASC')
            ->limit(500);
//        print_r($resultados->toSql());
//        die();
        $resultados = $resultados->get(['gp_articulos.*', 'gp_unidad_medidas.UMEDIDA_BASE AS ART_PLAN_UMEDIDA']);
        return $resultados;
    }

    public static function busquedaitemsl($buscar): \Illuminate\Support\Collection
    {
        $resultados = self::join('gp_unidad_medidas', 'gp_unidad_medidas.UMEDIDA_PLAN', '=', 'gp_articulos.ART_PLAN_UMEDIDA')
            ->where('gp_articulos.ART_ESTADO', 'ACTIVO')
            ->where('gp_articulos.ART_CLASE_ARTICULO','!=',  'BNS-INACT')
            ->where('gp_articulos.ART_CLASE_ARTICULO','!=',  'CMP-GASTOS')
            ->where('gp_articulos.ART_TIPO_COMPRA','L');
        if(isset($buscar['text_nombre']) && $buscar['text_nombre'] != ''){
            if($buscar['select_nombre'] == 'empieza'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', $buscar['text_nombre'].'%');
            }
            if($buscar['select_nombre'] == 'contiene'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%'.$buscar['text_nombre'].'%');
            }
            if($buscar['select_nombre'] == 'termina'){
                $resultados = $resultados->where('gp_articulos.ART_DESCRIPCION', 'LIKE', '%'.$buscar['text_nombre']);
            }
        }
        $resultados = $resultados->inRandomOrder()
            ->limit(500)
            ->get(['gp_articulos.*', 'gp_unidad_medidas.UMEDIDA_BASE AS ART_PLAN_UMEDIDA']);
        return $resultados;
    }
}
