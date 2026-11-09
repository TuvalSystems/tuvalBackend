<?php

namespace App\Services\Ventas\Cotizaciones\Borradores\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface QuotationItemsDraftServiceInterface
{
    public function create(array $data, GpUsuario $user): array;
}
