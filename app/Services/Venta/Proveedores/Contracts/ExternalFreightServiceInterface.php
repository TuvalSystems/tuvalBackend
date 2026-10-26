<?php

namespace App\Services\Venta\Proveedores\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Compras\Proveedores\FleteExterno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExternalFreightServiceInterface
{
    // Define los métodos
    public function listExternalFreights(array $filters): LengthAwarePaginator;
    public function createExternalFreight(array $data, GpUsuario $user): FleteExterno;
    public function getExternalFreightDetail(int $id): FleteExterno;
    public function updateExternalFreight(int $id, array $data, GpUsuario $user): FleteExterno;
    public function updateExternalFreightStatus(int $id): FleteExterno;
    public function validateExternalFreightIdentity(string $identify, ?int $excludeId = null) : bool;

    public function getExternalFreightsByProviderAndSalesPoint(int $provId, int $pvId) : Collection;
}
