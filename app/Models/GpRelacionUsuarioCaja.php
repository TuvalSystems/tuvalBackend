<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionUsuarioCaja
 *
 * @property int $id
 * @property string $CAJ_USER_USUARIO
 * @property string $CAJ_USER_CAJA
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCAJUSERCAJA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCAJUSERUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionUsuarioCaja whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionUsuarioCaja extends Model
{
    use HasFactory;
}
