<?php

namespace App\Services\Cobranzas\CierreCajas\Contracts;

use App\Models\GpUsuario;

interface CashRegisterClosureServiceInterface
{
    // Define los métodos
    public function cashCloseBox(int $boxId, int $pvId, string $pvCode, GpUsuario $user): array;
}
