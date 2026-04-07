<?php

namespace App\Models\Cobranzas_temp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobro
 *
 * @property int $id
 * @property string $numero_recibo
 * @property int $user_id
 * @property int $cliente_id
 * @property string|null $caja
 * @property string $fecha
 * @property string $estado
 * @property string $pedido
 * @property string|null $referencia
 * @property string|null $alerta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereAlerta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereCaja($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereNumeroRecibo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereReferencia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro whereUserId($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobro wherePedido($value)
 */
class GpCobro extends Model
{
    use HasFactory;
}
