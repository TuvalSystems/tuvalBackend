<?php

namespace App\Models\Admin\Empresas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresas extends Model
{
    use HasFactory;
    protected $table = 'empresas';

    protected $fillable = [
        'codigo',
        'ruc',
        'nombre',
        'direccion',
        'telefono',
        'resolucion_contribuyente_especial',
        'tolerancia_doc_sin_soporte',
        'ruc_proveedor_fact_electronica'
    ];

    protected $casts = [
        'codigo'                            => 'integer',
        'resolucion_contribuyente_especial' => 'integer',
        'tolerancia_doc_sin_soporte'        => 'decimal:2',
    ];
}
