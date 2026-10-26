<?php

namespace App\Models\Inventario\Despachos;

use App\Models\Ventas\Pedidos\GpPedido;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Inventario\Despachos\GpOrdenDespacho
 *
 * @property int $id
 * @property int $pedido_id
 * @property string|null $orden
 * @property string $estado
 * @property string $PTO_VTA_ID
 * @property string $BOD_BODEGA
 * @property string|null $autorizacion_fecha
 * @property int|null $autorizacion_user_id
 * @property string|null $autorizacion_USERID
 * @property string|null $autorizacion_comentario
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fecha_hora_liberacion
 * @property-read \App\Models\Ventas\Pedidos\GpPedido $pedido
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionComentario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereFechaHoraLiberacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereOrden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho wherePTOVTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpOrdenDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_id',
        'orden',
        'estado',
        'PTO_VTA_ID',
        'BOD_BODEGA',
        'autorizacion_fecha',
        'autorizacion_user_id',
        'autorizacion_USERID',
        'autorizacion_comentario',
        'fecha_hora_liberacion'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s'
    ];




    public function pedido(): BelongsTo
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
