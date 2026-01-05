<?php

namespace App\Observers\Ventas;

use App\Models\GpPedido;

class GpPedidoObserver
{
    public function created(GpPedido $order){
        $code = 'PED'.str_pad($order->id, 8, '0', STR_PAD_LEFT);
        $order->updateQuietly(['pedido' => $code]);
    }
}
