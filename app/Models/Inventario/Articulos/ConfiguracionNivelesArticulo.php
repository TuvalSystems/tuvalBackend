<?php

namespace App\Models\Inventario\Articulos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionNivelesArticulo extends Model
{
    use HasFactory;

    protected $table = 'configuracion_niveles_articulos';

    protected $fillable = [
        'nivel',
        'color',
        'descripcion',
        'estado',
    ];


    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('estado', 1);
    }

}
