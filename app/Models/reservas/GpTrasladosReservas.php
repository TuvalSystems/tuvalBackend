<?php

namespace App\Models\reservas;

use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpTrasladosReservas extends Model
{
    use HasFactory;

    protected $appends = ['pending_transfer'];
    protected $casts = [
        'pending_transfer' => 'int',
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


    public function detalleCotizacion() {
        return $this->belongsTo(GpCotizacionDetalle::class, 'detalle_id');
    }
    public function stock(){
        return $this->belongsTo(GpCotizacionDetalleStock::class, 'stock_id');
    }


    public function getPendingTransferAttribute()
    {
       $cant = $this->cantidad ?? 0;
       $res = $this->reservado ?? 0;
       $tras = $this->trasladado ?? 0;
       return max(0, $cant - $res - $tras);
    }
}
