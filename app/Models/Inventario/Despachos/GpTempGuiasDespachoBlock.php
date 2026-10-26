<?php

namespace App\Models\Inventario\Despachos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\Despachos\GpTempGuiasDespachoBlock
 *
 * @property int $id
 * @property int $guia_id
 * @property string|null $pedido
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock whereGuiaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempGuiasDespachoBlock whereUserId($value)
 * @mixin \Eloquent
 */
class GpTempGuiasDespachoBlock extends Model
{
    use HasFactory;

    protected $table = 'gp_temp_guias_despacho_blocks';

    protected $fillable = [
        'guia_id',
        'pedido',
        'user_id'
    ];
}
