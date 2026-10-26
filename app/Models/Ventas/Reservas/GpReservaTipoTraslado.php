<?php

namespace App\Models\Ventas\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Reservas\GpReservaTipoTraslado
 *
 * @property int $id
 * @property string $descripcion
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReservaTipoTraslado whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpReservaTipoTraslado extends Model
{
    use HasFactory;
    protected $table = 'gp_reserva_tipo_traslado';
    protected $fillable = [
        'id',
        'descripcion',
    ];
}
