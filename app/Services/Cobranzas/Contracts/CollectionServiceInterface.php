<?php

namespace App\Services\Cobranzas\Contracts;

use App\Models\GpUsuario;

interface CollectionServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function listPointSalesByUSer(GpUsuario $user);
    public function listBoxesByPointSales(string $pv, GpUsuario $user);
    public function getBoxClosureByDate(int $boxId, GpUsuario $user, string $date);
}
