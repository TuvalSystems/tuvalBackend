<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobroValidacionFacturaDetalle extends Model
{
    use HasFactory;

    protected $table = 'cobro_validacion_factura_detalles';

    protected $fillable = [
        'validacion_factura_id',
        'tipo_origen',
        'tipo_pago',
        'codigo_tipo_pago',
        'documento',
        'numero_recibo',
        'monto',
        'fecha_documento',
        'fecha_aplicacion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_documento' => 'date',
        'fecha_aplicacion' => 'date',
    ];

    public function factura()
    {
        return $this->belongsTo(CobroValidacionFactura::class, 'validacion_factura_id');
    }
}
