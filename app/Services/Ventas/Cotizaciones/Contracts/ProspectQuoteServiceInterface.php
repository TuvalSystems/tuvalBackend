<?php

namespace App\Services\Ventas\Cotizaciones\Contracts;


interface ProspectQuoteServiceInterface
{
    // Define los métodos

    public function restoreProspectQuote(string $ruc): array;
}
