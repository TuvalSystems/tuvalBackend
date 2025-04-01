<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionPuntoVentaIdVenta
 *
 * @property int $id
 * @property string $PTO_VTA_ID_FACTURA_NC
 * @property string $PTO_VTA_ID_PUNTO_VENTA
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta wherePTOVTAIDFACTURANC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta wherePTOVTAIDPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaIdVenta whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionPuntoVentaIdVenta extends Model
{
    use HasFactory;
}
