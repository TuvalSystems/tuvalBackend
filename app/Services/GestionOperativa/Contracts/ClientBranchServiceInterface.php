<?php

namespace App\Services\GestionOperativa\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface ClientBranchServiceInterface
{
    public function listClientBranches(array $filters): LengthAwarePaginator;
    public function getDetailClientBranches(int $id): array;
}
