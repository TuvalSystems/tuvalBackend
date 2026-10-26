<?php

namespace App\Services\Ventas\Clientes\Contracts;

use App\Models\Ventas\Clientes\GpClienteSucursal;

interface ClientGpSenderServiceInterface
{
    public function syncClient(int $clientId): void;

    public function syncBranch(int $branchId): void;
    public function sendClientBranchApiGp(array $params): array;
    public function createRegisterBranchClient(GpClienteSucursal $data, int $mode, ?string $userIngreso = null, ?string $fechaCreacion = null, ?string $userModificacion = null, ?string $fechaModificacion = null, bool $autoRetry = true): void;
}
