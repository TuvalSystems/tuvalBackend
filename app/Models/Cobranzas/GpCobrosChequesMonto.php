<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Cobranzas\GpCobrosChequesMonto
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $monto
 * @property string $saldo
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereSaldo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesMonto whereUpdatedAt($value)
 */
class GpCobrosChequesMonto extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobro_id',
        'monto',
        'saldo',
        'codigo_ws',
        'mensaje_ws',
        'enviado_gp'
    ];

    protected $casts = [
        'cobro_id'      => 'integer',
        'enviado_gp'    => 'boolean'
    ];
}
