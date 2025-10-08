<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpCotizacionReservaDetalleLog extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_detalle_stocks_logs';
    protected $fillable = [
        'ct_reserva_id',
        'stock_id',
        'art_code',
        'bodega',
        'cantidad',
    ];
}
