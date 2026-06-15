<?php

namespace App\Models\Articulos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionNivelesArticulo extends Model
{
    use HasFactory;

    protected $table = 'configuracion_niveles_articulos';

    protected $fillable =[
        'nivel',
        'color',
        'descripcion',
        'estado',
    ];
}
