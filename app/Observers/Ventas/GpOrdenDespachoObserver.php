<?php

namespace App\Observers\Ventas;

use App\Models\Inventario\Despachos\GpOrdenDespacho;

class GpOrdenDespachoObserver
{
    public function created(GpOrdenDespacho $ord)
    {
        $code = 'ODD' . str_pad($ord->id, 8, '0', STR_PAD_LEFT);
        $ord->updateQuietly(['orden' => $code]);
    }
}
