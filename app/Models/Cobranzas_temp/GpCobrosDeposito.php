<?php

namespace App\Models\Cobranzas_temp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosDeposito
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $estado
 * @property string|null $tipo
 * @property string|null $banco
 * @property string|null $comprobante
 * @property string|null $fecha
 * @property string $monto
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereBanco($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosDeposito whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosDeposito extends Model
{
    use HasFactory;
}
