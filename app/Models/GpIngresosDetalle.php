<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpIngresosDetalle
 *
 * @property int $id
 * @property int $ingreso_id
 * @property string|null $ART_CODIGO
 * @property string|null $ingresado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereIngresado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereIngresoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpIngresosDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpIngresosDetalle extends Model
{
    use HasFactory;
}
