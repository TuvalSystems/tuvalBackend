<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCaja
 *
 * @property int $id
 * @property string $CAJA_CODIGO
 * @property string $CAJA_DESCRIPCION
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCAJACODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCAJADESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCaja extends Model
{
    use HasFactory;
}
