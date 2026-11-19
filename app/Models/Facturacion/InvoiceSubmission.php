<?php

namespace App\Models\Facturacion;

use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\Facturacion\InvoiceSubmissionStatus;

class InvoiceSubmission extends Model
{
    protected $table = 'envios_facturacion';
    protected $primaryKey = 'pedido_id';
    public $incrementing = false;
    protected $guarded = ['*'];
    protected $casts = [
        'estado' => InvoiceSubmissionStatus::class, 'pedido_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
