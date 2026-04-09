<?php

namespace App\Models\Cobranzas;

use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Cobranzas\PermisosUsuarioPvCajas
 *
 * @property int $id
 * @property int $user_id
 * @property int $pv_id
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpPuntoVenta $puntoVenta
 * @property-read GpUsuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas query()
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas wherePvId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermisosUsuarioPvCajas whereUserId($value)
 * @mixin \Eloquent
 */
class PermisosUsuarioPvCajas extends Model
{
    use HasFactory;
    protected $table = 'permisos_usuarios_pv_cajas';
    protected $fillable = [
        'user_id',
        'pv_id',
        'estado',
    ];


    public function puntoVenta()
    {
        return $this->belongsTo(GpPuntoVenta::class, 'pv_id');
    }


    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'user_id');
    }
}
