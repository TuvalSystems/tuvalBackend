<?php
namespace App\Services\Venta\Cotizaciones\Contracts;

use App\Models\GpCotizacion;

interface QuotationHistoryCreatorServiceInterface
{
    // Define los métodos

    public function createQuoteHistory(GpCotizacion $quote): void;
}
