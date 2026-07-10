<?php

namespace App\Services\Cobranzas\CobrosFacturas\Contracts;

use App\Models\GpUsuario;

interface InvoiceCollectionServiceInterface
{
    // Define los métodos

    public function getStatusBoxedUser(int $pvId, string $pvCode, ?string $date, GpUsuario $user): array;
    public function openCashBoxed(int $pvId,  GpUsuario $user);
}
