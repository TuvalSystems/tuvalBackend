<?php

namespace App\Models\reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpTipoReservas extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'estado',
        'dias_max_reserva',
    ];
}
