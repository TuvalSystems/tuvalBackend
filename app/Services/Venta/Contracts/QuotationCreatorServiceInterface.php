<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpCotizacion;
use App\Models\GpUsuario;

interface QuotationCreatorServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createOrUpdate(array $data, GpUsuario $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): GpCotizacion;
}
