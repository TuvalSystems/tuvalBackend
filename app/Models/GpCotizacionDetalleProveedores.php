<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCotizacionDetalleProveedores
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property int $detalle_id
 * @property int $ID_PROVEEDOR
 * @property string $DESCRIPCION_ARTICULO
 * @property string $FECHA_ENTREGA
 * @property string $CANTIDAD
 * @property string $COSTO_UNITARIO
 * @property string $IMPUESTO
 * @property string $TOTAL
 * @property string $TOTALCONIMPUESTO
 * @property string|null $NOTA
 * @property string $MARGEN
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereCOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereDESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereNOTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereTOTALCONIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleProveedores whereMARGEN($value)
 */
class GpCotizacionDetalleProveedores extends Model
{
    use HasFactory;
}
