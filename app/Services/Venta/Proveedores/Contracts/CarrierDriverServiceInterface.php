<?php

namespace App\Services\Venta\Proveedores\Contracts;

use App\Models\GpUsuario;
use App\Models\Ventas\Proveedores\ChoferTransportista;
use Illuminate\Pagination\LengthAwarePaginator;

interface CarrierDriverServiceInterface
{
    // Define los métodos
    public function listCarrierDrivers(array $filters): LengthAwarePaginator;
    public function createRegisterCarrier(array $data, GpUsuario $user): ChoferTransportista;
    public function getDetailCarrierDriver(int $id): ChoferTransportista;
    public function updateRegisterCarrier(int $id, array $data, GpUsuario $user): ChoferTransportista;
    public function updateStatusCarrier(int $id): ChoferTransportista;
    public function validateCarrierIdentify(string $identify, ?int $excludeId = null) : bool;
}
