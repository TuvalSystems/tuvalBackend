<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpPedidoReservaDetalleLog extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_reserva_detalles_logs';
    protected $fillable = [
        'pedido_reserva_id',
        'stock_id',
        'art_code',
        'bodega',
        'cantidad',
    ];
}
