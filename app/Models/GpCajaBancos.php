<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCajaBancos
 *
 * @property int $id
 * @property string $CB_ID
 * @property string $CB_DESCRIPCION
 * @property string $CB_TIPO
 * @property string $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereCBDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereCBID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereCBTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCajaBancos whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCajaBancos extends Model
{
    use HasFactory;
}
