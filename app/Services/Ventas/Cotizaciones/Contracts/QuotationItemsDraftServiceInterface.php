<?php

namespace App\Services\Ventas\Cotizaciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface QuotationItemsDraftServiceInterface
{
    public function create(array $data, GpUsuario $actor): array;
}
