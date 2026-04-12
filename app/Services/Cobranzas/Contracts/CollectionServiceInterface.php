<?php

namespace App\Services\Cobranzas\Contracts;

use App\Models\GpUsuario;

interface CollectionServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function listPointSalesByUSer(GpUsuario $user);
    public function listBoxesByPointSales(int $pvId, GpUsuario $user);
    public function getBoxClosureByDate(int $boxId,  string $date);
    public function generateCashClosurePdfByUser(int $boxId, string $date);
    public function generateCashClosurePdf(int $boxId, string $date);
}
