<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

/**
 * App\Models\GpUsuario
 *
 * @property int $id
 * @property string|null $USERID
 * @property string|null $USERNAME
 * @property int|null $perfil_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $password
 *
 * @propertu int|null $status
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Sanctum\PersonalAccessToken[] $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario wherePerfilId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUSERNAME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $NIVEL_AUTORIZA_CRED
 * @method static \Illuminate\Database\Eloquent\Builder|GpUsuario whereNIVELAUTORIZACRED($value)
 * @property int|null  $must_change_password
 * @property int|null $process_job
 */
class GpUsuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    public $timestamps = true;
    protected $fillable = [
        'USERID',
        'USERNAME',
        'NIVEL_AUTORIZA_CRED',
        'perfil_id',
        'co_usr',
        'password',
        'must_change_password',
        'status',
        'process_job'

    ];
    protected $hidden = [
        'password',
    ];
}
