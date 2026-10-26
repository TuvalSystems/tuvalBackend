<?php

namespace App\Repositories\Cobranzas\CobrosFacturas\Contracts;

use App\Models\Cobranzas\Cajas\GpCaja;
use App\Models\Admin\Usuarios\GpUsuario;

interface InvoiceCollectionRepositoryInterface
{
    public function queryGetRelationShipUserBox(GpUsuario $user, ?int $pvId = null, ?string $pvCode = null): ?GpCaja;
}
