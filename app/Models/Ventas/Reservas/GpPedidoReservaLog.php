<?php

namespace App\Models\Ventas\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Reservas\GpPedidoReservaLog
 *
 * @property int $id
 * @property int|null $reserva_id
 * @property int|null $cotizacion_origen_id
 * @property int|null $pedido_destino_id
 * @property int $user_id
 * @property string|null $bodega
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereBodega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereCotizacionOrigenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog wherePedidoDestinoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaLog whereUserId($value)
 * @mixin \Eloquent
 */
class GpPedidoReservaLog extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_reserva_logs';

    protected $fillable = [
        'reserva_id',
        'cotizacion_origen_id',
        'pedido_destino_id',
        'user_id',
        'bodega',
    ];
}
