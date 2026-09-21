<?php

namespace App\Models\Ventas\Cotizaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'descripcion',
        'observacion',
        'usuario_ingreso_id',
        'fecha_ingreso',
        'usuario_modifica_id',
        'fecha_modificacion',
        'responsable_pago',
        'estado',
    ];

    protected $casts = [
        'descripcion'           => 'string',
        'observacion'           => 'string',
        'usario_ingreso_id'     => 'integer',
        'fecha_ingreso'         => 'datetime:Y-m-d H:i:s',
        'usuario_modifica_id'   => 'integer',
        'fecha_modificacion'    => 'datetime:Y-m-d H:i:s',
        'estado'                => 'boolean',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];
}
