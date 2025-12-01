<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpPedidoReservaLog extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_reserva_logs';

    protected $fillable = [
        'reserva_id',
        'cotizacion_origen_id',
        'pedido_destino_id',
        'user_id',
        'bodega',
    ];
}
