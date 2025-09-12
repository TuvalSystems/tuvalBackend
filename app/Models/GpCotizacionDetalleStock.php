<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCotizacionDetalleStock
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property int $detalle_id
 * @property string $STO_ART_BODEGA
 * @property string|null $STO_ART_STOCK_DISPONIBLE
 * @property string|null $seleccionado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSTOARTBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSTOARTSTOCKDISPONIBLE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $cantidad_autorizada_reserva
 * @property int|null $cantidad_facturada
 * @property int|null $cantidad_cancelada
 */
class GpCotizacionDetalleStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'detalle_id',
        'STO_ART_BODEGA',
        'STO_ART_STOCK_DISPONIBLE',
        'seleccionado',
        'cantidad_autorizada_reserva',
        'cantidad_facturada',
        'cantidad_cancelada'
    ];

}
