<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpOrdenDespacho
 *
 * @property int $id
 * @property int $pedido_id
 * @property string|null $orden
 * @property string $estado
 * @property string $PTO_VTA_ID
 * @property string $BOD_BODEGA
 * @property string|null $autorizacion_fecha
 * @property string|null $fecha_hora_liberacion
 * @property int|null $autorizacion_user_id
 * @property string|null $autorizacion_USERID
 * @property string|null $autorizacion_comentario
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionComentario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereAutorizacionUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDespacho whereEstado($value)
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
}
