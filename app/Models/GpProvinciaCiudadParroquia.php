<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpProvinciaCiudadParroquia
 *
 * @property int $id
 * @property string $provincia
 * @property string $ciudad
 * @property string $parroquia
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia whereCiudad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia whereParroquia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProvinciaCiudadParroquia whereProvincia($value)
 * @mixin \Eloquent
 */
class GpProvinciaCiudadParroquia extends Model
{
    use HasFactory;
}
