<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionPuntoVentaBodega
 *
 * @property int $id
 * @property string $PTO_VTA_BOD_BODEGA
 * @property string $PTO_VTA_BOD_PUNTO_VENTA
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega wherePTOVTABODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega wherePTOVTABODPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodega whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionPuntoVentaBodega extends Model
{
    use HasFactory;

    protected $fillable = [
        'PTO_VTA_BOD_BODEGA',
        'PTO_VTA_BOD_PUNTO_VENTA',
    ];
}
