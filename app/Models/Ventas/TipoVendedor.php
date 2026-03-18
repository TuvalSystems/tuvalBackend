<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVendedor extends Model
{
    use HasFactory;

    protected $table = 'tipo_vendedors';
    protected $fillable = [
        'tipo_vendedor',
        'codigo',
        'descripcion',
        'estado',
    ];
}
