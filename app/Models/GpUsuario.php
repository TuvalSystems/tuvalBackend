<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 */
class GpUsuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    public $timestamps = true;
    protected $fillable = [
        'USERID',
        'USERNAME',
    ];
}
