<?php

namespace App\Repositories\Cobranzas\Contracts;

use App\Models\GpCierreCaja;
use App\Models\GpUsuario;

interface CashClosureRepositoryInterface
{
    public function isBoxClosed(int $boxId, string $date, int $userId): bool;
    public function getBoxClosureSummary(int $boxId, string $date, int $userId): ?GpCierreCaja;
}
