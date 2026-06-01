<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\Cobranzas\GpCobro
 *
 * @property int $id
 * @property string $numero_recibo
 * @property int $user_id
 * @property int $cliente_id
 * @property int|null $caja_id
 * @property string|null $caja
 * @property string $fecha
 * @property string $estado
 * @property string|null $pedido
 * @property string|null $referencia
 * @property string|null $alerta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fecha_aprobacion_rechazo
 * @property int|null $user_aprobador_id
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereAlerta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereCaja($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereCajaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereFechaAprobacionRechazo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereNumeroRecibo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereReferencia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereUserAprobadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereUserId($value)
 * @mixin \Eloquent
 */
class GpCobro extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_recibo',
        'user_id',
        'cliente_id',
        'caja_id',
        'caja',
        'fecha',
        'estado',
        'pedido',
        'referencia',
        'alerta',
        'fecha_aprobacion_rechazo',
        'user_aprobador_id'
    ];

    public function cheques()
    {
        return $this->hasMany(GpCobrosCheques::class, 'cobro_id', 'id');
    }
}
