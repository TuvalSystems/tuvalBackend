<?php

namespace App\Models\Facturacion;

use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\Facturacion\InvoiceRequestStatus;

class InvoiceRequest extends Model
{
    protected $table = 'solicitudes_facturacion';
    protected $primaryKey = 'id';
    protected $guarded = ['*'];
    protected $casts = [
        'estado' => InvoiceRequestStatus::class, 'directa' => 'boolean',
        'trasladado' => 'boolean',
        'check_demanda' => 'boolean',
        'version' => 'integer',
        'pedido_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
