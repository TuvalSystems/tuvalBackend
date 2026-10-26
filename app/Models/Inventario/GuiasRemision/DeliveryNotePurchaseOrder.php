<?php

namespace App\Models\Inventario\GuiasRemision;

use Illuminate\Database\Eloquent\Model;

class DeliveryNotePurchaseOrder extends Model
{
    protected $table = 'guia_remision_orden_compra';

    protected $guarded = ['id'];

    protected $casts = [
        'pedido_id' => 'integer',
        'guia_remision_id' => 'integer',
        'guia_reserva_id' => 'integer',
        'intentos' => 'integer',
        'solicitud' => 'array',
        'respuesta' => 'array',
        'fecha_intento' => 'datetime',
        'fecha_generacion' => 'datetime',
        'fecha_proximo_intento' => 'datetime',
    ];
}
