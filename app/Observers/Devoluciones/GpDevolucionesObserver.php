<?php

namespace App\Observers\Devoluciones;

use App\Models\devoluciones\GpDevoluciones;

class GpDevolucionesObserver
{
    public function created(GpDevoluciones $devolution)
    {
        $code = 'DEV' . str_pad($devolution->id, 8, "0", STR_PAD_LEFT);
        $devolution->updateQuietly(['devolucion' => $code]);
    }
}
