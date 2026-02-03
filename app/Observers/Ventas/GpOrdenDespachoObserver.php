<?php

namespace App\Observers\Ventas;

use App\Models\GpOrdenDespacho;

class GpOrdenDespachoObserver
{
    public function created(GpOrdenDespacho $ord)
    {
        $code = 'ODD' . str_pad($ord->id, 8, '0', STR_PAD_LEFT);
        $ord->updateQuietly(['orden' => $code]);
    }
}
