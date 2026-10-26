<?php
namespace App\Services\Ventas\Cotizaciones\Contracts;

use App\Models\Ventas\Cotizaciones\GpCotizacion;

interface QuotationHistoryCreatorServiceInterface
{
    // Define los métodos

    public function createQuoteHistory(GpCotizacion $quote): void;
}
