<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpCotizacionReservaDetalleLog extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_detalle_stocks_logs';
    protected $fillable = [
        'creserva_id',
        'stock_id',
        'user_id',
        'tipo',
        'cantidad',
    ];
}
