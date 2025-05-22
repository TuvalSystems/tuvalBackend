<?php

namespace App\Services\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionVentaMotivo;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class QuotationService implements QuotationServiceInterface
{


    public function getNonSalesReasons(): Collection
    {
        return GpCotizacionVentaMotivo::orderBy('id')->get();
    }

    public function updateNonSaleAndReminderData($data)
    {
        return GpCotizacion::where('id', $data['cotizacion_id'])->update([
            'fecha_recordatorio' => $data['fecha_recordatorio'],
            'cotizacion_venta_motivo_id' => $data['motivo_id'],
            'observacion' => $data['observacion'],
            'recordar' => $data['recordar'],

        ]);
    }
}
