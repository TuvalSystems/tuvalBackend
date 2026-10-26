<?php

namespace App\Models\Ventas\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Reservas\GpPedidoReservaDetalleLog
 *
 * @property int $id
 * @property int $pedido_reserva_id
 * @property int|null $stock_id
 * @property string $art_code
 * @property string $bodega
 * @property string $cantidad
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereArtCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereBodega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog wherePedidoReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereStockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoReservaDetalleLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpPedidoReservaDetalleLog extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_reserva_detalles_logs';
    protected $fillable = [
        'pedido_reserva_id',
        'stock_id',
        'art_code',
        'bodega',
        'cantidad',
    ];
}
