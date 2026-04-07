<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpTipoReservas
 *
 * @property int $id
 * @property string $descripcion
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTipoReservas whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTipoReservas extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'estado',
        'dias_max_reserva',
    ];
}
