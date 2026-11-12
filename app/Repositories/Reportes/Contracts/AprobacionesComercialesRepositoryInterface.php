<?php

declare(strict_types=1);

namespace App\Repositories\Reportes\Contracts;

interface AprobacionesComercialesRepositoryInterface
{
    /** @return list<object> */
    public function consultar(string $tipoDocumento, int $documentoId): array;
}
