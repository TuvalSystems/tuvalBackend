<?php

namespace App\Models\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class GpDevolucionResponsablePagoTransporte extends Model
{
    protected $table = 'gp_devolucion_responsables_pago_transporte';
    protected $fillable = ['codigo', 'descripcion', 'estado'];
    protected $casts = ['estado' => 'boolean'];
}
