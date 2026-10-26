<?php

namespace App\Models\Inventario\Articulos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\Articulos\GpArticuloSugerido
 *
 * @property int $id
 * @property string $ART_CODIGO
 * @property int $ART_ADI_CANTIDAD
 * @property string $ART_ADI_SUGERIDO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereARTADICANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereARTADISUGERIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpArticuloSugerido whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpArticuloSugerido extends Model
{
    use HasFactory;
}
