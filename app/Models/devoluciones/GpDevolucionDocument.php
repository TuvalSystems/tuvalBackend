<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\devoluciones\GpDevolucionDocument
 *
 * @property int $id
 * @property int $devolucion_id
 * @property string $devolucion
 * @property int|null $id_movimiento
 * @property string|null $documento_gp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereDevolucionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereDocumentoGp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereIdMovimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevolucionDocument whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpDevolucionDocument extends Model
{
    use HasFactory;

    protected $fillable =[
        'devolucion_id',
        'devolucion',
        'id_movimiento',
        'documento_gp',
    ];
}
