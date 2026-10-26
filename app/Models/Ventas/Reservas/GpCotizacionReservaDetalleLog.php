<?php

namespace App\Models\Ventas\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Reservas\GpCotizacionReservaDetalleLog
 *
 * @property int $id
 * @property int|null $ct_reserva_id
 * @property int|null $stock_id
 * @property string $art_code
 * @property string $bodega
 * @property string $cantidad
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereArtCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereBodega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereCtReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereStockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaDetalleLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCotizacionReservaDetalleLog extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_reserva_detalles_logs';
    protected $fillable = [
        'ct_reserva_id',
        'stock_id',
        'art_code',
        'bodega',
        'cantidad',
    ];
}
