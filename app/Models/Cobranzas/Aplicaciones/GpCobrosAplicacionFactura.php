<?php

namespace App\Models\Cobranzas\Aplicaciones;

use App\Models\Cobranzas\Cobros\GpCobro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Cobranzas\Aplicaciones\GpCobrosAplicacionFactura
 *
 * @property int $id
 * @property int $cobro_id
 * @property int $aplicacion_id
 * @property string $id_factura
 * @property string $monto
 * @property string|null $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereAplicacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionFactura whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosAplicacionFactura extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobro_id',
        'aplicacion_id',
        'id_factura',
        'monto',
        'observacion',
        'estado_aplicado',
        'user_desaplicacion_id',
        'fecha_desaplicacion',
        'observacion_desaplicacion'
    ];

    protected $casts = [
        'cobro_id'      => 'integer',
        'aplicacion_id' => 'integer',
        'monto'         => 'float',
    ];




    public function aplicacionMonto(): BelongsTo
    {
        return $this->belongsTo(GpCobrosAplicacionMonto::class, 'aplicacion_id', 'id');
    }

    public function cobro()
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
    }

    public function cobroPendienteWeb(): BelongsTo
    {
        return $this->belongsTo(
            GpCobro::class,
            'cobro_id',
            'id'
        )
        ->whereNotIn('estado', ['rechazado', 'no_enviado']);
    }

    public function scopePendientesWeb(Builder $query, ?string $desde = null, ?string $hasta = null, array $numeroRecibos = []): Builder
    {
        return $query->whereHas('cobroPendienteWeb', function ($q) use ($desde, $hasta, $numeroRecibos) {
            if ($desde && $hasta) {
                $q->whereBetween('created_at', [$desde, $hasta]);
            }
            if (!empty($numeroRecibos)) {
                $q->whereNotIn('numero_recibo', $numeroRecibos);
            }
        });
    }
}
