<?php

declare(strict_types=1);

namespace App\Repositories\Reportes\Contracts;

interface AprobacionesComercialesRepositoryInterface
{
    /** @return list<object> */
    public function consultar(?string $cotizacion, ?string $factura, ?string $cliente, ?string $sucursal, ?string $aprobadorFinal, string $periodo): array;
}
