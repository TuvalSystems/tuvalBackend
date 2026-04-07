<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosEfectivoMonto
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $monto
 * @property string|null $fecha
 * @property string|null $banco
 * @property string|null $comprobante
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereBanco($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosEfectivoMonto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosEfectivoMonto extends Model
{
    use HasFactory;
}
