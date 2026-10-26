<?php

namespace App\Models\Admin\Notificaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \DateTimeInterface;

/**
 * App\Models\Admin\Notificaciones\Notificaciones
 *
 * @property int $id
 * @property string|null $titulo
 * @property int|null $user_id
 * @property string|null $mensaje
 * @property string $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones query()
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereTitulo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Notificaciones whereUserId($value)
 * @mixin \Eloquent
 */
class Notificaciones extends Model
{
    use HasFactory;
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */

     protected $fillable = [
        'titulo',
        'user_id',
        'mensaje',
        'estado',
    ];
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
