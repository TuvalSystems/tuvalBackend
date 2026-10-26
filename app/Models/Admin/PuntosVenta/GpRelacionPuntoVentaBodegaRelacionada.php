<?php

namespace App\Models\Admin\PuntosVenta;

use App\Models\Inventario\Bodegas\GpBodega;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodegaRelacionada
 *
 * @property int $id
 * @property string $PTO_VTA_BOD_REL_BODEGA
 * @property string $PTO_VTA_BOD_REL_PUNTO_VENTA
 * @property string|null $PTO_VTA_BOD_REL_NIVEL_ACCESO
 * @property int|null $orden
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Admin\PuntosVenta\GpPuntoVenta $puntoVenta
 * @property-read \App\Models\Inventario\Bodegas\GpBodega $relacionBodega
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereOrden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELNIVELACCESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionPuntoVentaBodegaRelacionada extends Model
{
    use HasFactory;

    protected $fillable = [
        'PTO_VTA_BOD_REL_BODEGA',
        'PTO_VTA_BOD_REL_PUNTO_VENTA',
        'PTO_VTA_BOD_REL_NIVEL_ACCESO',
        'orden'
    ];



    public function relacionBodega(){
        return $this->belongsTo(GpBodega::class, 'PTO_VTA_BOD_REL_BODEGA' , 'BOD_BODEGA');
    }


   public function puntoVenta(){
        return $this->belongsTo(GpPuntoVenta::class, 'PTO_VTA_BOD_REL_PUNTO_VENTA' , 'PTO_VTA_ID');
    }
}
