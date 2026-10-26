<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Illuminate\Support\Facades\DB;

class GpCotizacionObserver
{
    public function __construct(
        protected readonly QuotationServiceInterface $quotationService
    ) {}

    public function created(GpCotizacion $quote)
    {
        $code = 'COT' . str_pad($quote->id, 8, '0', STR_PAD_LEFT);
        $quote->updateQuietly(['cotizacion' => $code]);
    }

    public function updated(GpCotizacion $quote): void
    {
        if (is_null($quote->cotizacion_venta_motivo_id)) {
            return;
        }

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn() => $this->syncStoreReminderGp($quote));
            return;
        }

        $this->syncStoreReminderGp($quote);
    }

    protected function syncStoreReminderGp(GpCotizacion $quote): void
    {
        $quote->loadMissing(['usuario', 'usuariorecordatorio', 'motivosNoVenta']);
        $this->quotationService->sendStoreReminderGp($quote);
    }
}
