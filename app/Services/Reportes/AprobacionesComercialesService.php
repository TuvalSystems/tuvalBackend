<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Repositories\Reportes\Contracts\AprobacionesComercialesRepositoryInterface;
use App\Services\Reportes\Contracts\AprobacionesComercialesServiceInterface;

class AprobacionesComercialesService implements AprobacionesComercialesServiceInterface
{
    public function __construct(
        private readonly AprobacionesComercialesRepositoryInterface $repository,
    ) {
    }

    public function consultar(string $tipoDocumento, int $documentoId): array
    {
        $aprobaciones = [];

        foreach ($this->repository->consultar($tipoDocumento, $documentoId) as $resultado) {
            $aprobacion = (array) $resultado;

            foreach ($aprobacion as $columna => $valor) {
                if (strtolower(str_replace('_', '', $columna)) === 'detallejson' && is_string($valor)) {
                    $aprobacion[$columna] = json_decode($valor, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                }
            }

            $aprobaciones[] = $aprobacion;
        }

        return $aprobaciones;
    }
}
