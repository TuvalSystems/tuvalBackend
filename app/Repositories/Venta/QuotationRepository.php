<?php

namespace App\Repositories\Venta;

use App\Models\GpCotizacion;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;

class QuotationRepository implements QuotationRepositoryInterface
{
    public function getScheduledReminders()
    {
        $hoy = now()->startOfDay();
        return GpCotizacion::with('detallecotizacion', 'usuario', 'motivosNoVenta')->whereNotNull('cotizacion_venta_motivo_id')
            ->whereDate('fecha_recordatorio', $hoy)
            ->where('recordar', 1)
            ->get();


    }

}
