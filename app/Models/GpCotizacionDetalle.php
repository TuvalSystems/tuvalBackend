<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCotizacionDetalle
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_DESCRIPCION_ORIGINAL
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTCOSTOPROMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTDECIMALCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTDESCRIPCIONORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTDESCUENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTIMPVTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTSTOCK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTVALORORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalle whereARTPESO($value)
 * @property boolean|null $CHECK_ALTERNATIVO
 */
class GpCotizacionDetalle extends Model
{
    use HasFactory;
    protected $fillable = [
        'cotizacion_id',
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
        'ART_IMP_VTA',
        'ART_COSTO_PROMEDIO',
        'descuento',
        'descuento_original',
        'modificador',
        'CHECK_ALTERNATIVO',
        'valor_original_l'

    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }

    public function proveedores()
    {
        return $this->hasMany(GpCotizacionDetalleProveedores::class, 'detalle_id');
    }

    public function stock()
    {
        return $this->hasMany(GpCotizacionDetalleStock::class, 'detalle_id');
    }


    public function articulo()
    {
        return $this->belongsTo(GpArticulo::class, 'ART_CODIGO', 'ART_CODIGO');
    }
}
