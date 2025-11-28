<?php

namespace App\Models\reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
