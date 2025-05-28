<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpCotizacionVentaMotivo extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_venta_motivos';
    protected $fillable =[
        'id',
        'descripcion',
        'recordatorio',
        'estado'
    ];
}
