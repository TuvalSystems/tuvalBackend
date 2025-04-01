<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCobrosTarjetaFactura
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $id_factura
 * @property string $monto
 * @property string|null $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaFactura whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosTarjetaFactura extends Model
{
    use HasFactory;
}
