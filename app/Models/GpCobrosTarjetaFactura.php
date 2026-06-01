<?php

namespace App\Models;

use App\Models\Cobranzas\GpCobro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'cobro_id',
        'id_factura',
        'monto',
        'observacion'
    ];

    protected $casts = [
        'cobro_id'  => 'integer',
        'monto'     => 'float'
    ];


    public function cobro(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
    }


    public function cobroPendienteWeb(): BelongsTo
    {
        return $this->belongsTo(
            GpCobro::class,
            'cobro_id',
            'id'
        );
        //->whereIn('estado', ['iniciado', 'revisado']);
    }

    public function scopePendientesWeb(Builder $query, ?string $desde = null, ?string $hasta = null, array $rcsPorExcluir = []): Builder
    {
        return $query->whereHas('cobroPendienteWeb', function ($q) use ($desde, $hasta, $rcsPorExcluir) {
            if ($desde && $hasta) {
                $q->whereBetween('created_at', [$desde, $hasta]);
            }
            if (!empty($rcsPorExcluir)) {
                $q->whereNotIn('numero_recibo', $rcsPorExcluir);
            }
        });
    }
}
