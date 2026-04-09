<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpCotizacionReservaLog
 *
 * @property int $id
 * @property int|null $reserva_id
 * @property int|null $cotizacion_origen_id
 * @property int|null $cotizacion_destino_id
 * @property int $user_id
 * @property string $bodega
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereBodega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereCotizacionDestinoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereCotizacionOrigenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionReservaLog whereUserId($value)
 * @mixin \Eloquent
 */
class GpCotizacionReservaLog extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_reserva_logs';

    protected $fillable = [
        'reserva_id',
        'cotizacion_origen_id',
        'cotizacion_destino_id',
        'user_id',
        'bodega',
    ];
}
