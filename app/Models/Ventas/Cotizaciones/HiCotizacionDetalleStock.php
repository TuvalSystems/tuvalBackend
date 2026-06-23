<?php

namespace App\Models\Ventas\Cotizaciones;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiCotizacionDetalleStock extends Model
{
    use HasFactory;

    protected $table = 'hi_cotizacion_detalle_stocks';

    protected $fillable = [
        'hi_cot_id',
        'hi_cot_det_id',
        'cot_id',
        'det_id',
        'STO_ART_BODEGA',
        'STO_ART_STOCK_DISPONIBLE',
        'seleccionado',
        'cantidad_autorizada_reserva',
        'cantidad_facturada',
        'cantidad_reservada',
        'cantidad_cotizada',
        'cantidad_traslado',
        'cantidad_transito',
        'cantidad_cancelada',
    ];

    protected $casts = [
        'hi_cot_id'                     => 'integer',
        'hi_cot_det_id'                 => 'integer',
        'cot_id'                        => 'integer',
        'det_id'                        => 'integer',
        'STO_ART_STOCK_DISPONIBLE'      => 'decimal:2',
        'seleccionado'                  => 'decimal:2',
        'cantidad_autorizada_reserva'   => 'decimal:2',
        'cantidad_facturada'            => 'decimal:2',
        'cantidad_reservada'            => 'decimal:2',
        'cantidad_cotizada'             => 'decimal:2',
        'cantidad_traslado'             => 'decimal:2',
        'cantidad_transito'             => 'decimal:2',
        'cantidad_cancelada'            => 'decimal:2',
    ];

    public function hiCotizacion()
    {
        return $this->belongsTo(HiCotizacion::class, 'hi_cot_id');
    }

    public function hiCotizacionDetalle()
    {
        return $this->belongsTo(HiCotizacionDetalle::class, 'hi_cot_det_id');
    }

    public function cotizacionOriginal()
    {
        return $this->belongsTo(GpCotizacion::class, 'cot_id');
    }

    public function detalleOriginal()
    {
        return $this->belongsTo(GpCotizacionDetalle::class, 'det_id');
    }
}
