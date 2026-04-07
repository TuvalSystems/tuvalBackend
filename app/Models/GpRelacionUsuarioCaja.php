<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionUsuarioCaja
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $caja_id
 * @property string $CAJ_USER_USUARIO
 * @property string $CAJ_USER_CAJA
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCAJUSERCAJA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCAJUSERUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCajaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereUserId($value)
 * @property-read \App\Models\GpCaja|null $caja
 * @property-read \App\Models\User|null $usuario
 * @mixin \Eloquent
 */
class GpRelacionUsuarioCaja extends Model
{
    use HasFactory;

    protected $table = 'gp_relacion_usuario_cajas';

    protected $fillable = [
        'user_id',
        'caja_id',
        'estado'
    ];



    public function caja()
    {
        return $this->belongsTo(GpCaja::class, 'caja_id');
    }


    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
