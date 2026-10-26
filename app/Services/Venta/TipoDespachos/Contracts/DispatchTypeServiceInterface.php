<?php

namespace App\Services\Venta\TipoDespachos\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Inventario\Despachos\TipoDespacho;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DispatchTypeServiceInterface
{
    // Define los métodos

    public function list(GpUsuario $user, array $filters = []): LengthAwarePaginator;
    public function create(array $data, GpUsuario $user);
    public function getDetail(int $id): TipoDespacho;
    public function update(int $id, array $data, GpUsuario $user);
    public function activeOrInactive(int $id);
    public function getActivePointSales(): Collection;
    public function getDispatchTypesByPaymentResponsible(int $pvId, string $paymentResponsible): Collection;
}
