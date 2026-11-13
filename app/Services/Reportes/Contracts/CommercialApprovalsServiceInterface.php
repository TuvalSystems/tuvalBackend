<?php

declare(strict_types=1);

namespace App\Services\Reportes\Contracts;

interface CommercialApprovalsServiceInterface
{
    /** @return list<array<string, mixed>> */
    public function getApprovals(?string $cotizacion, ?string $factura, ?string $cliente, ?string $sucursal, ?string $aprobadorFinal, string $periodo): array;
}
