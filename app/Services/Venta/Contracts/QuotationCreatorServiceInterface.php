<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpCotizacion;

interface QuotationCreatorServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function createOrUpdate(array $data,  $user, array $files = []): GpCotizacion;
}
