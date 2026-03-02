<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpGuiaRemisionReservaMovimiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'guia_id',
        'id_movimiento',
        'tipo_movimiento',
        'tipo',
        'cotizacion',
        'codigo',
        'hash_interno'
    ];
}
