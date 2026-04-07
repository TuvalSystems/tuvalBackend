<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpRelacionPuntoVentaCaja
 *
 * @property int $id
 * @property int|null $caja_id
 * @property int|null $pv_id
 * @property string $PTO_VTA_CAJA_CAJA
 * @property string $PTO_VTA_CAJA_PUNTO_VENTA
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja whereCajaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja wherePTOVTACAJACAJA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja wherePTOVTACAJAPUNTOVENTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja wherePvId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpRelacionPuntoVentaCaja whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpRelacionPuntoVentaCaja extends Model
{
    use HasFactory;
}
