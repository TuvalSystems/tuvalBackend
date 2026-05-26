<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Cobranzas\GpCobrosDepositosFacturas
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

    protected $fillable = [
        'cobro_id',
        'deposito_id',
        'id_factura',
        'monto',
        'observacion'
    ];

    protected $casts = [
        'cobro_id'      => 'integer',
        'deposito_id'   => 'integer',
        'monto'         => 'float'
    ];


    public function cobro(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
    }

    public function cobroDeposito(): BelongsTo
    {
        return $this->belongsTo(GpCobrosDeposito::class, 'deposito_id', 'id');
    }

    public function cobroPendienteWeb(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
            //->whereIn('estado', ['iniciado', 'revisado']);
    }

    public function scopePendientesWeb(Builder $query, ?string $desde = null, ?string $hasta = null): Builder
    {
        return $query->whereHas('cobroPendienteWeb', function ($q) use ($desde, $hasta) {
            if ($desde && $hasta) {
                $q->whereBetween('created_at', [$desde, $hasta]);
            }
        });
    }
}
