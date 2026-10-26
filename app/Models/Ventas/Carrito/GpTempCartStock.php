<?php

namespace App\Models\Ventas\Carrito;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Carrito\GpTempCartStock
 *
 * @property int $id
 * @property int $id_tempcart
 * @property string $STO_ART_BODEGA
 * @property string|null $STO_ART_STOCK_DISPONIBLE
 * @property string|null $seleccionado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereIdTempcart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereSTOARTBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereSTOARTSTOCKDISPONIBLE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartStock whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTempCartStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_tempcart',
        'STO_ART_BODEGA',
        'STO_ART_STOCK_DISPONIBLE',
        'seleccionado',
    ];
}
