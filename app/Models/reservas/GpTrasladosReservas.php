<?php

namespace App\Models\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpTrasladosReservas
 *
 * @property int $id
 * @property int|null $reserva_id
 * @property int|null $cotizacion_id
 * @property int|null $detalle_id
 * @property int|null $stock_id
 * @property string|null $art_code
 * @property string|null $art_descripcion
 * @property string|null $art_cod_tres_letras
 * @property string|null $art_plan_umedida
 * @property string|null $art_peso
 * @property string|null $cantidad
 * @property string|null $bodega_origen
 * @property string|null $bodega_destino
 * @property string|null $reservado
 * @property string|null $trasladado
 * @property int $completado
 * @property int $picking
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpCotizacion|null $cotizacion
 * @property-read GpCotizacionDetalle|null $detalleCotizacion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservas\GpGuiaRemisionReservaDetalle> $detalleguia
 * @property-read int|null $detalleguia_count
 * @property-read bool $check_disable_button_picking
 * @property-read int|float $pending_transfer
 * @property-read \App\Models\Reservas\GpReserva|null $reserva
 * @property-read GpCotizacionDetalleStock|null $stock
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas pending()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereArtCodTresLetras($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereArtCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereArtDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereArtPeso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereArtPlanUmedida($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereCompletado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas wherePicking($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereStockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereTrasladado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReservas whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTrasladosReservas extends Model
{
    use HasFactory;

    protected $table = 'gp_traslados_reservas';
    protected $appends = ['pending_transfer', 'check_disable_button_picking'];
    protected $casts = [
        'pending_transfer' => 'float',
        'check_disable_button_picking' => 'bool',
    ];
    protected $fillable = [
        'reserva_id',
        'cotizacion_id',
        'detalle_id',
        'stock_id',
        'art_code',
        'art_descripcion',
        'art_cod_tres_letras',
        'art_plan_umedida',
        'art_peso',
        'cantidad',
        'bodega_origen',
        'bodega_destino',
        'reservado',
        'trasladado',
        'completado',
        'picking',
    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }

    public function detalleCotizacion()
    {
        return $this->belongsTo(GpCotizacionDetalle::class, 'detalle_id');
    }
    public function stock()
    {
        return $this->belongsTo(GpCotizacionDetalleStock::class, 'stock_id');
    }

    public function detalleguia()
    {
        return $this->hasMany(GpGuiaRemisionReservaDetalle::class, 'traslado_id');
    }

    public function reserva()
    {
        return $this->belongsTo(GpReserva::class, 'reserva_id');
    }


    public function getPendingTransferAttribute(): float|int
    {
        $cant = $this->cantidad ?? 0;
        $res = $this->reservado ?? 0;
        $tras = $this->trasladado ?? 0;
        return max(0, $cant - $res - $tras);
    }


    public function scopePending($q)
    {
        return $q->where(
            fn($q) =>
            $q->whereNull('completado')->orWhere('completado', 0)
        );
    }

    public function getCheckDisableButtonPickingAttribute(): bool
    {
        return $this->detalleguia()->whereHas('guia', function ($query) {
            $query->where('estado', '!=', 'eliminada');
        })->exists();

    }
}
