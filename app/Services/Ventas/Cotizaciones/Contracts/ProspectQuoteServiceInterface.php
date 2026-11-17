<?php

namespace App\Services\Ventas\Cotizaciones\Contracts;


use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Cotizaciones\GpCotizacion;

interface ProspectQuoteServiceInterface
{
    public function create(array $data, GpUsuario $user): GpCotizacion;

    public function completeLegacyFields(GpCotizacion $quote): void;

    public function restoreProspectQuote(string $ruc): array;
}
