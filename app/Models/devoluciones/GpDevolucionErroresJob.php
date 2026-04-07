<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\devoluciones\GpDevolucionErroresJob
 *
 * @property int $id
 * @property int $devolucion_id
 * @property int|null $criterio_id
 * @property string|null $mensaje
 * @property string|null $proceso
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereCriterioId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereDevolucionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereProceso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionErroresJob whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpDevolucionErroresJob extends Model
{
    use HasFactory;

    protected $table = 'gp_devolucion_errores_jobs';
    protected $fillable = [
        'devolucion_id',
        'criterio_id',
        'proceso',
        'mensaje',
        'created_at',
        'updated_at'
    ];
}
