<?php

namespace App\Services\Venta\Cotizaciones\Contracts;

use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface DispatchTypeServiceInterface
{
    // Define los métodos

    public function list(GpUsuario $user, array $filters = []): LengthAwarePaginator;
    public function create(array $data, GpUsuario $user);
    public function update(int $id, array $data, GpUsuario $user);
    public function activeOrInactive(int $id);
}
