<?php

declare(strict_types=1);

namespace App\Repositories\Reportes;

use App\Repositories\Reportes\Contracts\AprobacionesComercialesRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AprobacionesComercialesRepository implements AprobacionesComercialesRepositoryInterface
{
    public function consultar(?string $cotizacion, ?string $factura, ?string $cliente, ?string $sucursal, ?string $aprobadorFinal, string $periodo): array
    {
        return DB::connection('mysql')->select(
            'CALL tuvalapi.sp_consultar_aprobaciones_pedido(?, ?, ?, ?, ?, ?)',
            [$cotizacion, $factura, $cliente, $sucursal, $aprobadorFinal, $periodo],
        );
    }
}
