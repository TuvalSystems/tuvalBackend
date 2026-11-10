<?php

declare(strict_types=1);

namespace App\Services\Reportes\Contracts;

interface AprobacionesComercialesServiceInterface
{
    /** @return list<array<string, mixed>> */
    public function consultar(string $tipoDocumento, int $documentoId): array;
}
