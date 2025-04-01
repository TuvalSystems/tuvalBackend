<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpBodega
 *
 * @property int $id
 * @property string $BOD_BODEGA
 * @property string|null $BOD_DESCRIPCION
 * @property string $BOD_ESTADO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $PERMITE_DESPACHO
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega wherePERMITEDESPACHO($value)
 */
class GpBodega extends Model
{
    use HasFactory;
}
