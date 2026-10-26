<?php

namespace App\Services\Ventas\Cotizaciones\Contracts;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Admin\Usuarios\GpUsuario;

interface QuotationCreatorServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createOrUpdate(array $data, GpUsuario $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): GpCotizacion;
}
