<?php

namespace App\Services\GestionOperativa\Contracts;

use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClientBranchServiceInterface
{
    public function listClientBranches(array $filters): LengthAwarePaginator;
    public function getDetailClientBranches(int $id): array;
    public function updateStatusClientBranches(int $id, int $estado, GpUsuario $user): GpClienteSucursal;
    public function createClientBranch(array $data, GpUsuario $user) : GpClienteSucursal;

}
