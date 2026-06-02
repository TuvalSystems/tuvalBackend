<?php

namespace App\Repositories\Venta;

use App\Models\GpCotizacion;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class QuotationRepository implements QuotationRepositoryInterface
{
    const FINAL_STATES = ['caducado', 'procesado', 'rechazado', 'aprobado', 'eliminado'];
    public function getScheduledReminders()
    {
        $hoy = now()->startOfDay();
        return GpCotizacion::with('detallecotizacion', 'usuario', 'motivosNoVenta')->whereNotNull('cotizacion_venta_motivo_id')
            ->whereDate('fecha_recordatorio', $hoy)
            ->where('recordar', 1)
            ->get();
    }




    /** query para lista de cotizacions pendientes de aprobacion comercial */
    public function fetchPendingQuotes(int $ptoVta): Builder
    {
        $query = GpCotizacion::query();
        $query->join('gp_descuento_forma_pagos as dfp', 'dfp.DESC_FORMA_PAGO_CODIGO', 'gp_cotizacions.DESC_FORMA_PAGO_CODIGO')
            ->select(
                'gp_cotizacions.*',
                'dfp.DESC_FORMA_PAGO_TIPO_CREDITO'
            )
            ->where('requiere_aprobacion_comercial', 'yes')
            ->where('aprobado_comercial', 'no')
            ->whereNotIn('gp_cotizacions.estado', self::FINAL_STATES)
            ->orderBy('created_at', 'ASC');
        return $query;
    }
}
