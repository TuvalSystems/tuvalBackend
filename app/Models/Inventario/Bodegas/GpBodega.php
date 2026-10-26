<?php

namespace App\Models\Inventario\Bodegas;

use App\Models\Ventas\Cotizaciones\GpCotizacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\Bodegas\GpBodega
 *
 * @property int $id
 * @property string $BOD_BODEGA
 * @property string|null $BOD_DESCRIPCION
 * @property string $BOD_ESTADO
 * @property int|null $PERMITE_DESPACHO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Cotizaciones\GpCotizacion> $cotizacion
 * @property-read int|null $cotizacion_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereBODESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega wherePERMITEDESPACHO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpBodega whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpBodega extends Model
{
    use HasFactory;


    public function cotizacion(){
        return $this->hasMany(GpCotizacion::class, 'BOD_BODEGA', 'BOD_BODEGA');
    }
}
