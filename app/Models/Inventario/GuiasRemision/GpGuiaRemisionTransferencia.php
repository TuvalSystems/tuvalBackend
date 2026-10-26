<?php

namespace App\Models\Inventario\GuiasRemision;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\GuiasRemision\GpGuiaRemisionTransferencia
 *
 * @property int $id
 * @property int $guia_remision_id
 * @property int|null $id_movimiento
 * @property string|null $tipo_movimiento
 * @property string|null $tipo Ingreso o Egreso
 * @property string $codigo
 * @property string|null $pedido
 * @property string|null $hash_interno UUID para trazabilidad con la API de bodega
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereGuiaRemisionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereHashInterno($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereIdMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereTipoMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionTransferencia whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionTransferencia extends Model
{
    use HasFactory;

    protected $fillable = [
        'guia_remision_id',
        'id_movimiento',
        'tipo_movimiento',
        'tipo',
        'codigo',
        'pedido',
        'hash_interno'
    ];
}
