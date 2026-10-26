<?php

namespace App\Services\Cobranzas\Cajas\Contracts;

use App\Models\Cobranzas\Cajas\GpCierreCaja;
use App\Models\Admin\Usuarios\GpUsuario;

interface CashRegisterClosureServiceInterface
{
    // Define los métodos
    public function cashCloseBox(int $boxId, int $pvId, string $pvCode, GpUsuario $user): GpCierreCaja;
    public function getCashBoxClosureSummary(int $boxId, int $pvId, string $pvCode, string $date, GpUsuario $user): array;
}
