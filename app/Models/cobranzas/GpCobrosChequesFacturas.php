<?php

namespace App\Models\cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosChequesFacturas
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $monto
 * @property string $id_factura
 * @property string|null $observacion
 * @property string|null $ECD_DET_FECHA_VENCIMIENTO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereECDDETFECHAVENCIMIENTO($value)
 */
class GpCobrosChequesFacturas extends Model
{
    use HasFactory;
}
