<?php

namespace App\Models\Ventas\Aprobaciones;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Aprobaciones\GpReglaNegocio
 *
 * @property int $id
 * @property string $perfil_aprobador
 * @property string|null $nombre
 * @property string|null $descripcion
 * @property string|null $observacion1
 * @property string|null $observacion2
 * @property string|null $funcion
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $fecha_ingreso
 * @property int|null $user_ingreso_id
 * @property string|null $fecha_modificacion
 * @property int|null $user_modificacion_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereFechaIngreso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereFechaModificacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereFuncion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereObservacion1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereObservacion2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio wherePerfilAprobador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereUserIngresoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReglaNegocio whereUserModificacionId($value)
 * @mixin \Eloquent
 */
class GpReglaNegocio extends Model
{
    use HasFactory;

    protected $table =  'gp_regla_negocios';

    protected $casts = [
        'fecha_ingreso' => 'datetime:Y-m-d H:i:s',
    ];


    protected $fillable = [
        'perfil_aprobador',
        'nombre',
        'descripcion',
        'observacion1',
        'observacion2',
        'funcion',
        'estado',
        'fecha_ingreso',
        'user_ingreso_id',
        'fecha_modificacion',
        'user_modificacion_id',
    ];


    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

}
