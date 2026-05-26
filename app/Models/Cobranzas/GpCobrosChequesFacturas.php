<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Cobranzas\GpCobrosChequesFacturas
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereECDDETFECHAVENCIMIENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereIdFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosChequesFacturas whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosChequesFacturas extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobro_id',
        'monto',
        'id_factura',
        'observacion',
        'ECD_DET_FECHA_VENCIMIENTO',
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
        );//->whereIn('estado', ['iniciado', 'revisado']);
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
