<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Repositories\Reportes\Contracts\AprobacionesComercialesRepositoryInterface;
use App\Services\Reportes\Contracts\CommercialApprovalsServiceInterface;

class CommercialApprovalsService implements CommercialApprovalsServiceInterface
{
    public function __construct(
        private readonly AprobacionesComercialesRepositoryInterface $repository,
    ) {
    }

    public function getApprovals(string $documentType, int $documentId): array
    {
        $approvals = [];

        foreach ($this->repository->consultar($documentType, $documentId) as $result) {
            $approval = (array) $result;

            foreach ($approval as $column => $value) {
                if (strtolower(str_replace('_', '', $column)) === 'detallejson' && is_string($value)) {
                    $approval[$column] = json_decode($value, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                }
            }

            $approvals[] = $approval;
        }

        return $approvals;
    }
}
