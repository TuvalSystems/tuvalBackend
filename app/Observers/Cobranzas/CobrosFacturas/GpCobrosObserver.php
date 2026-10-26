<?php

namespace App\Observers\Cobranzas\CobrosFacturas;

use App\Models\Cobranzas\Cobros\GpCobro;

class GpCobrosObserver
{
    public function created(GpCobro $collection)
    {
        $code = 'RC-' . str_pad($collection->id, 8, '0', STR_PAD_LEFT);
        $collection->updateQuietly(['numero_recibo' => $code]);
    }
}
