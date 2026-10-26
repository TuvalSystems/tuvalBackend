<?php

namespace App\Models\Inventario\GuiasRemision;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\GuiasRemision\GpGuiaRemisionDespacho
 *
 * @property int $id
 * @property int|null $id_movimiento
 * @property int $guia_remision_id
 * @property string|null $tipo_movimiento
 * @property string|null $tipo Ingreso o Egreso
 * @property string $codigo
 * @property string|null $pedido
 * @property string|null $hash_interno UUID para trazabilidad con la API de bodega
 * @property string|null $lote_despacho_id UUID del evento de despacho parcial
 * @property int|null $secuencia_despacho Secuencia del despacho parcial por guia/pedido/tipo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventario\GuiasRemision\GpGuiaRemision $guiaRemision
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereGuiaRemisionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereHashInterno($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereIdMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereLoteDespachoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereSecuenciaDespacho($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereTipoMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDespacho whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_movimiento',
        'guia_remision_id',
        'tipo_movimiento',
        'tipo',
        'codigo',
        'pedido',
        'hash_interno',
        'lote_despacho_id',
        'secuencia_despacho'
    ];


    public function guiaRemision()
    {
        return $this->belongsTo(GpGuiaRemision::class, 'guia_remision_id');
    }
}
