<?php

namespace App\Models\Ventas\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoIdentificacion extends Model
{
    use HasFactory;

    protected $table = 'tipo_identificacions';
    protected $fillable = [
        'codigo',
        'descripcion',
        'min_size',
        'max_size',
        'estado',
    ];

    protected $casts = [
        'min_size' => 'integer',
        'max_size' => 'integer',
        'estado' => 'boolean',
    ];
}
