<?php

namespace App\Services\Venta\Proveedores\Contracts;

use App\Models\GpUsuario;
use App\Models\Ventas\Proveedores\GpProveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProviderServiceInterface
{
    // Define los métodos
    public function listProviders(array $filters, GpUsuario $user): LengthAwarePaginator;
    public function updateCarrierStatus(int $id, GpUsuario $user): void;
    public function updateStatus(int $id, GpUsuario $user): void;
    public function listProviderTypeCarrier(int $pvId): Collection;
    public function getAllProviders(): Collection;
    public function getDetailProvider(int $id) : GpProveedor;
    public function syncPotinSalesByProvider(int $id, array $pvIds) : GpProveedor;
}
