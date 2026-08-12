<?php

namespace App\Repositories\Cobranzas\CobrosFacturas\Contracts;

use App\Models\GpCaja;
use App\Models\GpUsuario;

interface InvoiceCollectionRepositoryInterface
{
    public function queryGetRelationShipUserBox(GpUsuario $user, ?int $pvId = null, ?string $pvCode = null): ?GpCaja;
}
