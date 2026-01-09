<?php

namespace App\Models\devoluciones;

use App\Enums\devoluciones\InvoiceAnulationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpFacturaAnulada extends Model
{
    use HasFactory;

    protected $table = 'gp_factura_anuladas';

    protected $casts = [
        'estado' => InvoiceAnulationStatus::class
    ];
    protected $fillable = [
        'devolucion_id',
        'pedido_id',
        'factura',
        'pedido',
        'documento_id',
        'claveacceso',
        'msj_anulacion_gp',
        'diario_response_gp',
        'resp_document',
        'resp_seedbilling',
        'estado'
    ];
}
