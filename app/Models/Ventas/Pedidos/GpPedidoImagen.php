<?php

namespace App\Models\Ventas\Pedidos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Pedidos\GpPedidoImagen
 *
 * @property int $id
 * @property int $pedido_id
 * @property string $ruta
 * @property string $nombre_original
 * @property string|null $tipo_mime
 * @property int|null $tamano
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ventas\Pedidos\GpPedido $cotizacion
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereNombreOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereRuta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereTamano($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereTipoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoImagen whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpPedidoImagen extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_imagenes';
    protected $fillable = [
        'pedido_id',
        'ruta',
        'nombre_original',
        'tipo_mime',
        'tamano',
    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
