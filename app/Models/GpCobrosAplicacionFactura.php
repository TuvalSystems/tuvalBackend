<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCobrosAplicacionFactura
 *
 * @property int $id
 * @property int $cobro_id
 * @property int $aplicacion_id
 * @property string $id_factura
 * @property string $monto
 * @property string|null $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereAplicacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosAplicacionFactura extends Model
{
    use HasFactory;
}
