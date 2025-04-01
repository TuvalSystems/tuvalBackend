<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\devoluciones\GpDevolucionesDetalle
 *
 * @property int $id
 * @property int $devolucion_id
 * @property int $detalle_id
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
 * @property string|null $seleccionado
 * @property string|null $disponible
 * @property string|null $recibido
 * @property string|null $devuelto
 * @property string|null $compartimiento
 * @property string|null $USERID
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTCOSTOPROMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTDECIMALCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTDESCRIPCIONORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTDESCUENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTIMPVTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTSTOCK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereARTVALORORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDevolucionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDevuelto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDetalleId($value)
 */
class GpDevolucionesDetalle extends Model
{
    use HasFactory;
}
