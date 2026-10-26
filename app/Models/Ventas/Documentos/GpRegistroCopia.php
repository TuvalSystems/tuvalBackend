<?php

namespace App\Models\Ventas\Documentos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Documentos\GpRegistroCopia
 *
 * @property int $id
 * @property int|null $pedido_origen
 * @property int|null $cotizacion_origen
 * @property int|null $cotizacion_destino
 * @property int|null $user_id
 * @property string|null $USERID
 * @property string $tipo
 * @property int $destino_aprobado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereCotizacionDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereCotizacionOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereDestinoAprobado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia wherePedidoOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRegistroCopia whereUserId($value)
 * @mixin \Eloquent
 */
class GpRegistroCopia extends Model
{
    use HasFactory;
    protected $fillable= [
        'pedido_origen',
        'cotizacion_origen',
        'cotizacion_destino',
        'user_id',
        'USERID',
        'tipo',
        'destino_aprobado'
    ];
}
