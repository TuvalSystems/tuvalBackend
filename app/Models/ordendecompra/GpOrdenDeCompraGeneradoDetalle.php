<?php

namespace App\Models\ordendecompra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle
 *
 * @property int $id
 * @property int $orden_generada_id
 * @property int $detalle_proveedores_id
 * @property string $ORDEN_COMPRA_DETALLE_BODEGA
 * @property string $ORDEN_COMPRA_DETALLE_ARTICULO
 * @property string $ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO
 * @property string $ORDEN_COMPRA_DETALLE_UMEDIDA
 * @property string $ORDEN_COMPRA_DETALLE_CANTIDAD
 * @property string $ORDEN_COMPRA_DETALLE_COSTO_UNITARIO
 * @property string $ORDEN_COMPRA_DETALLE_TOTAL
 * @property string $recibido
 * @property string $ORDEN_COMPRA_DETALLE_IMPUESTO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLEARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLEBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLECANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLECOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLEDESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLEIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLETOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereORDENCOMPRADETALLEUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereOrdenGeneradaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGeneradoDetalle whereDetalleProveedoresId($value)
 */
class GpOrdenDeCompraGeneradoDetalle extends Model
{
    use HasFactory;


    protected $fillable = [
        'orden_generada_id',
        'detalle_proveedores_id',
        'ORDEN_COMPRA_DETALLE_BODEGA',
        'ORDEN_COMPRA_DETALLE_ARTICULO',
        'ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO',
        'ORDEN_COMPRA_DETALLE_UMEDIDA',
        'ORDEN_COMPRA_DETALLE_CANTIDAD',
        'ORDEN_COMPRA_DETALLE_COSTO_UNITARIO',
        'ORDEN_COMPRA_DETALLE_TOTAL',
        'recibido',
        'ORDEN_COMPRA_DETALLE_IMPUESTO',
    ];
}
