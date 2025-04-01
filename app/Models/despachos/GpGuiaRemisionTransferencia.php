<?php

namespace App\Models\despachos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\despachos\GpGuiaRemisionTransferencia
 *
 * @property int $id
 * @property int $guia_remision_id
 * @property string $codigo
 * @property string $pedido
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereGuiaRemisionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia wherePedido($value)
 */
class GpGuiaRemisionTransferencia extends Model
{
    use HasFactory;
}
