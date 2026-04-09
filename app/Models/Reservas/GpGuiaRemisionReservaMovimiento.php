<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpGuiaRemisionReservaMovimiento
 *
 * @property int $id
 * @property int|null $guia_id
 * @property string|null $tipo_movimiento
 * @property string|null $tipo Ingreso o Egreso
 * @property int|null $id_movimiento
 * @property string|null $cotizacion
 * @property string $codigo
 * @property string|null $hash_interno UUID para trazabilidad con la API de bodega
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereCotizacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereGuiaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereHashInterno($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereIdMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereTipoMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaMovimiento whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionReservaMovimiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'guia_id',
        'id_movimiento',
        'tipo_movimiento',
        'tipo',
        'cotizacion',
        'codigo',
        'hash_interno'
    ];
}
