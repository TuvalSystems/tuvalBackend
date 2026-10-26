<?php

namespace App\Models\Inventario\Articulos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\Articulos\GpListaPrecio
 *
 * @property int $id
 * @property string|null $LST_PRC_ARTICULO
 * @property string|null $LST_PRC_MONEDA
 * @property string|null $LST_PRC_UMEDIDA
 * @property string|null $LST_PRC_ID
 * @property string|null $LST_PRC_VALOR
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereLSTPRCARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereLSTPRCID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereLSTPRCMONEDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereLSTPRCUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereLSTPRCVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpListaPrecio whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpListaPrecio extends Model
{
    use HasFactory;
}
