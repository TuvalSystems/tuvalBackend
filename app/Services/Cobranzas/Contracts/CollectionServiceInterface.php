<?php

namespace App\Services\Cobranzas\Contracts;

use App\Models\GpUsuario;

interface CollectionServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function listPointSalesByUSer(string $ptoVta, GpUsuario $user);
}
