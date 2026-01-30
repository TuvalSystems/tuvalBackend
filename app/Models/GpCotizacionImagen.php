<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpCotizacionImagen extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_imagenes';
    protected $fillable = [
        'cotizacion_id',
        'ruta',
        'nombre_original',
        'tipo_mime',
        'tamano',
    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }
}
