<?php

namespace App\Models\Facturacion;

use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Devoluciones\GpDevoluciones;

class ReturnReinvoice extends Model
{
    protected $table = 'refacturaciones_devolucion';
    protected $primaryKey = 'devolucion_id';
    public $incrementing = false;
    protected $guarded = ['*'];
    protected $casts = [
        'pedido_id' => 'integer',
        'devolucion_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(GpDevoluciones::class, 'devolucion_id');
    }
}
