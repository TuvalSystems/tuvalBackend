<?php

namespace App\Services\Venta\Cotizaciones\Contracts;


interface ProspectQuoteServiceInterface
{
    // Define los métodos

    public function restoreProspectQuote(string $ruc): array;
}
