<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpPedidoDetalleStock
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $detalle_id
 * @property string $STO_ART_BODEGA
 * @property string|null $STO_ART_STOCK_DISPONIBLE
 * @property string|null $seleccionado
 * @property string|null $reservado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereSTOARTBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereSTOARTSTOCKDISPONIBLE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleStock whereReservado($value)
 */
class GpPedidoDetalleStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_id',
        'detalle_id',
        'STO_ART_BODEGA',
        'STO_ART_STOCK_DISPONIBLE',
        'seleccionado',
        'reservado',
    ];


    public function detalle()
    {
        return $this->belongsTo(GpPedidoDetalle::class, 'detalle_id');
    }

    public function pedido(){
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
