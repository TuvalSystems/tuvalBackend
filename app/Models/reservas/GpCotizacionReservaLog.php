<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpCotizacionReservaLog extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_reserva_logs';

    protected $fillable = [
        'reserva_id',
        'cotizacion_origen_id',
        'cotizacion_destino_id',
        'user_id',
        'bodega',
    ];
}
