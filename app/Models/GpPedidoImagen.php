<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpPedidoImagen extends Model
{
    use HasFactory;

    protected $table = 'gp_pedido_imagenes';
    protected $fillable = [
        'pedido_id',
        'ruta',
        'nombre_original',
        'tipo_mime',
        'tamano',
    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
