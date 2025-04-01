<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionPuntoVentaBodegaRelacionada
 *
 * @property int $id
 * @property string $PTO_VTA_BOD_REL_BODEGA
 * @property string $PTO_VTA_BOD_REL_PUNTO_VENTA
 * @property string|null $PTO_VTA_BOD_REL_NIVEL_ACCESO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELNIVELACCESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada wherePTOVTABODRELPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $orden
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaBodegaRelacionada whereOrden($value)
 */
class GpRelacionPuntoVentaBodegaRelacionada extends Model
{
    use HasFactory;
}
