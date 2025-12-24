<?php

namespace App\Observers\Ventas;

use App\Models\GpCotizacion;

class GpCotizacionObserver
{
    public function created(GpCotizacion $quote)
    {
        $code = 'COT' . str_pad($quote->id, 8, '0', STR_PAD_LEFT);
        $quote->updateQuietly(['cotizacion' => $code]);
    }
}
