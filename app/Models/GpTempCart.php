<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpTempCart
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int $cliente_id
 * @property string $puntodeventa_id
 * @property int $user_id
 * @property string $USERID
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_DESCRIPCION_ORIGINAL
 * @property int $CHECK_ALTERNATIVO
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property int|null $ART_DECIMAL_CANTIDAD
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_MARCA
 * @property string|null $ART_MATERIAL
 * @property string|null $ART_PESO
 * @property string|null $ART_TIPO_COMPRA
 * @property string|null $ART_VALOR
 * @property string|null $ART_IVA
 * @property string|null $ART_VALOR_ORIGINAL
 * @property string|null $ART_DESCUENTO
 * @property string|null $ART_CANTIDAD
 * @property string|null $ART_STOCK
 * @property string|null $ART_IMP_VTA
 * @property string|null $ART_COSTO_PROMEDIO
 * @property string|null $descuento
 * @property string|null $descuento_original
 * @property string|null $modificador
 * @property int|null $orden
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $detalle_id
 * @property string|null $tipo
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTCOSTOPROMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTDECIMALCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTDESCRIPCIONORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTDESCUENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTIMPVTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTSTOCK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereARTVALORORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereCHECKALTERNATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereOrden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart wherePuntodeventaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCart whereUserId($value)
 * @mixin \Eloquent
 */
class GpTempCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'detalle_id',
        'sucursal_id',
        'cliente_id',
        'puntodeventa_id',
        'user_id',
        'USERID',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_DESCRIPCION_ORIGINAL',
        'ART_CODIGO_TRES_LETRAS',
        'ART_DECIMAL_CANTIDAD',
        'ART_PLAN_UMEDIDA',
        'ART_MARCA',
        'ART_MATERIAL',
        'ART_PESO',
        'ART_TIPO_COMPRA',
        'ART_VALOR',
        'ART_IVA',
        'ART_VALOR_ORIGINAL',
        'ART_DESCUENTO',
        'ART_CANTIDAD',
        'ART_STOCK',
        'ART_COSTO_PROMEDIO',
        'ART_IMP_VTA',
        'descuento',
        'descuento_original',
        'modificador',
        'orden',
        'CHECK_ALTERNATIVO',
        'tipo',
    ];
}
