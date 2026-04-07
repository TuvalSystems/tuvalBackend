<?php

namespace App\Models\Cobranzas_temp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosEfectivoFactura
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $id_factura
 * @property string $monto
 * @property string|null $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoFactura whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosEfectivoFactura extends Model
{
    use HasFactory;
}
