<?php

declare(strict_types=1);

namespace App\Repositories\Reportes;

use App\Repositories\Reportes\Contracts\AprobacionesComercialesRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AprobacionesComercialesRepository implements AprobacionesComercialesRepositoryInterface
{
    public function consultar(string $tipoDocumento, int $documentoId): array
    {
        return DB::connection('mysql')->select(
            'CALL tuvalapi.sp_consultar_aprobaciones_pedido(?, ?)',
            [$tipoDocumento, $documentoId],
        );
    }
}
