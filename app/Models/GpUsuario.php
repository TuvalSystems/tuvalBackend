<?php

namespace App\Models;

use App\Models\Ventas\Vendedores\GpVendedor;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

/**
 * App\Models\GpUsuario
 *
 * @property int $id
 * @property string $USERID
 * @property string|null $USERNAME
 * @property int|null $NIVEL_AUTORIZA_CRED
 * @property int|null $perfil_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $co_usr
 * @property string|null $password
 * @property int $status
 * @property int $must_change_password
 * @property int $process_job
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpCaja> $cajasAsignadas
 * @property-read int|null $cajas_asignadas_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Perfiles|null $perfil
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPuntoVenta> $puntoVentaPermitidos
 * @property-read int|null $punto_venta_permitidos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpRelacionUsuarioPuntoVenta> $relacionptovta
 * @property-read int|null $relacionptovta_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read GpVendedor|null $vendedor
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario activo()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereCoUsr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereMustChangePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereNIVELAUTORIZACRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario wherePerfilId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereProcessJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUSERNAME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpUsuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    public $timestamps = true;

    protected $casts = [
        'app_access'        => 'boolean',
        'is_admin_cobros'   => 'boolean',
        'status'            => 'boolean'
    ];
    protected $fillable = [
        'USERID',
        'USERNAME',
        'NIVEL_AUTORIZA_CRED',
        'perfil_id',
        'perfil_app_id',
        'co_usr',
        'password',
        'must_change_password',
        'status',
        'process_job',
        'is_admin_cobros',
        'app_access'

    ];
    protected $hidden = [
        'password',
    ];

     protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    public function relacionptovta()
    {
        return $this->hasMany(GpRelacionUsuarioPuntoVenta::class, 'USER_PTO_VTA_USUARIO', 'USERID');
    }

    public function vendedor()
    {
        return $this->hasOne(GpVendedor::class, 'user_id');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfiles::class, 'perfil_id');
    }

    public function perfilApp(){
        return $this->belongsTo(Perfiles::class, 'perfil_app_id');
    }


    public function scopeActivo($query)
    {
        return $query->where('status', 1)
            ->whereHas('perfil', function ($q) {
                $q->where('state', 'active');
            });
    }



    public function puntoVentaPermitidos()
    {
        return $this->belongsToMany(GpPuntoVenta::class, 'permisos_usuarios_pv_cajas', 'user_id', 'pv_id')->withPivot('estado')->wherePivot('estado', 1);
    }

    public function cajasAsignadas()
    {
        return $this->belongsToMany(GpCaja::class, 'gp_relacion_usuario_cajas', 'user_id', 'caja_id')
            ->withPivot('estado')
            ->wherePivot('estado', 1);
    }
}
