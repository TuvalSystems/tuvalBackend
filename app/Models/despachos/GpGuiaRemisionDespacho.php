<?php

namespace App\Models\despachos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpGuiaRemisionDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'guia_remision_id',
        'codigo',
        'pedido',
        'id_movimiento'
    ];


    public function guiaRemision()
    {
        return $this->belongsTo(GpGuiaRemision::class, 'guia_remision_id');
    }
}
