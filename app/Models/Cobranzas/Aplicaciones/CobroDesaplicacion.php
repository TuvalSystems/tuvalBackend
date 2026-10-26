<?php

namespace App\Models\Cobranzas\Aplicaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobroDesaplicacion extends Model
{
    use HasFactory;
    protected $table = 'cobro_desaplicaciones';

    protected $casts = [
        'fecha_aplicacion'      => 'datetime:Y-m-d H:i:s',
        'fecha_desaplicacion'   => 'datetime:Y-m-d H:i:s',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
        'monto_cobro'           => 'decimal:2',
        'monto_desaplicar'      => 'decimal:2',
        'estado'                => 'boolean',
    ];
    protected $fillable = [
        'cobro_id',
        'numero_recibo',
        'tipo_medio',
        'documento_desaplicar',
        'factura',
        'monto_cobro',
        'monto_desaplicar',
        'user_aplicacion_id',
        'fecha_aplicacion',
        'user_desaplicacion_id',
        'fecha_desaplicacion',
        'observacion',
        'estado',
    ];
}
