<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpReservaTipoTraslado extends Model
{
    use HasFactory;
    protected $table = 'gp_reserva_tipo_traslado';
    protected $fillable = [
        'id',
        'descripcion',
    ];
}
