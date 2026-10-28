<?php

namespace App\Models\Devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\Devoluciones\GpDevolucionesDetalle
 *
 * @property int $id
 * @property int $devolucion_id
 * @property int $detalle_id
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
 * @property string|null $seleccionado
 * @property string|null $devuelto
 * @property string|null $disponible
 * @property string|null $recibido
 * @property string|null $compartimiento
 * @property int $despachado_parcial Indica si el ítem fue despachado parcialmente
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $USERID
 * @property-read mixed $pending_received_devolution
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle articulosFisicos()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle validosParaNotaCredito()
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereCHECKALTERNATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereCompartimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDespachadoParcial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDevolucionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDevuelto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereDisponible($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereRecibido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionesDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpDevolucionesDetalle extends Model
{
    use HasFactory;
    const TIPO_SERVICIO = 'SERV';
    protected $table = 'gp_devoluciones_detalles';
    protected $appends = ['pending_received_devolution'];
    protected $fillable = [
        'es_servicio_logistico',
        'devolucion_id',
        'detalle_id',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_DESCRIPCION_ORIGINAL',
        'CHECK_ALTERNATIVO',
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
        'seleccionado',
        'disponible',
        'recibido',
        'devuelto',
        'compartimiento',
        'USERID',
        'despachado_parcial'
    ];




    protected function getPendingReceivedDevolutionAttribute()
    {
        $select = (float) $this->seleccionado;
        $devuelto = (float) $this->devuelto;
        $resultado = max(0, $select - $devuelto);
        return number_format($resultado, 2, '.', '');
    }



    /** scopes para notas de credito  */

    public function scopeArticulosFisicos($query)
    {
        return $query->where('ART_TIPO_COMPRA', '!=', self::TIPO_SERVICIO)
            ->where('es_servicio_logistico', false);
    }

    public function scopeValidosParaNotaCredito($query)
    {
        return $query->where('despachado_parcial', 1)
            ->where('devuelto', '>', 0)
            ->where('recibido', '>', 0);
    }
}
