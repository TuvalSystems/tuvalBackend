<?php

namespace App\Models;

use App\Models\reservas\GpTrasladosReservas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCotizacionDetalleStock
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property int $detalle_id
 * @property string $STO_ART_BODEGA
 * @property string|null $STO_ART_STOCK_DISPONIBLE
 * @property string|null $seleccionado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSTOARTBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSTOARTSTOCKDISPONIBLE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereSeleccionado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionDetalleStock whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $cantidad_autorizada_reserva
 * @property int|null $cantidad_facturada
 * @property int|null $cantidad_cancelada
 * @property int|null $cantidad_cotizada
 * @property int|null $cantidad_traslado
 *
 *
 *
 * @property mixed $pendiente
 *
 */
class GpCotizacionDetalleStock extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_detalle_stocks';
    protected $appends = ['disable_movimiento', 'pendiente', 'disable_traslado', 'picking'];
    protected $casts = [
        'disable_movimiento' => 'boolean',
        'pendiente' => 'float',
        'disable_traslado' => 'boolean',
        'picking' => 'boolean',

        'cantidad_autorizada_reserva' => 'float',
        'cantidad_facturada' => 'float',
        'cantidad_cancelada' => 'float',
        'cantidad_cotizada' => 'float',
        'cantidad_traslado' => 'float',
        'cantidad_reservada' => 'float',
    ];
    protected $fillable = [
        'cotizacion_id',
        'detalle_id',
        'STO_ART_BODEGA',
        'STO_ART_STOCK_DISPONIBLE',
        'seleccionado',
        'cantidad_autorizada_reserva',
        'cantidad_facturada',
        'cantidad_cotizada',
        'cantidad_traslado',
        'user_id_facturacion',
        'cantidad_cancelada',
        'user_id_cancelacion',
        'cantidad_reservada',
    ];


    public function trasladoreserva()
    {
        return $this->hasMany(GpTrasladosReservas::class, 'stock_id');
    }

    protected function getReservada()
    {
        return (float) $this->cantidad_reservada ?? 0;
    }

    public function getPendienteAttribute(): float
    {
        $res = $this->getReservada();
        $fact = $this->cantidad_facturada ?? 0;
        $can = $this->cantidad_cancelada ?? 0;
        // $cot = $this->cantidad_cotizada     ?? 0;
        // $tras = $this->cantidad_traslado ?? 0;
        $pendiente = $res - $fact - $can;
        return max(0, round($pendiente, 4));
        // return max(0, $res - $fact - $can);
    }

    public function getDisableMovimientoAttribute(): bool
    {
        return $this->pendiente === 0;
    }

    public function getDisableTrasladoAttribute(): bool
    {
        return $this->cantidad_traslado > 0;
    }

    public function getPickingAttribute(): bool
    {
        return $this->trasladoreserva()->where('picking', 1)->exists();
    }
}
