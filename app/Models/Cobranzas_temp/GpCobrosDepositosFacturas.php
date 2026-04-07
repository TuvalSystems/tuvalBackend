<?php

namespace App\Models\Cobranzas_temp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosDepositosFacturas
 *
 * @property int $id
 * @property int $cobro_id
 * @property int $deposito_id
 * @property string $id_factura
 * @property string $monto
 * @property string|null $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereDepositoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDepositosFacturas whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosDepositosFacturas extends Model
{
    use HasFactory;
}
