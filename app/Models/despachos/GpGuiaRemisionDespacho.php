<?php

namespace App\Models\despachos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpGuiaRemisionDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_movimiento',
        'guia_remision_id',
        'tipo_movimiento',
        'tipo',
        'codigo',
        'pedido',
        'hash_interno'
    ];


    public function guiaRemision()
    {
        return $this->belongsTo(GpGuiaRemision::class, 'guia_remision_id');
    }
}
