<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Cobranzas\GpCobrosEfectivoFactura
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

    protected $fillable = [
        'cobro_id',
        'id_factura',
        'monto',
        'observacion'
    ];

    protected $casts = [
        'cobro_id' => 'integer',
        'monto'    => 'float'
    ];

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
    }

    public function cobroPendienteWeb(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id')
            ->whereNotIn('estado', ['rechazado', 'no_enviado']);
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
