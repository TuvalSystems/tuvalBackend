<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpGuiaRemisionReservaDetalle extends Model
{
    use HasFactory;

    protected $fillable = [
        'guia_id',
        'traslado_id',
        'cotizacion_id',
        'detalle_id',
        'stock_id',
        'art_code',
        'art_descripcion',
        'art_cod_tres_letras',
        'cantidad',
        'art_plan_umedida',
        'recibido'
    ];
}
