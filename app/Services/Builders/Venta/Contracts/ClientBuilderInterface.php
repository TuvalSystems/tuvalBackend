<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;

interface ClientBuilderInterface
{

    public function formPayloadNewAddress(array $data, GpCliente $cliente, array $direccion, string $email): array;
    public function formPayloadNewSucursal(array $data, GpCliente $cliente, string $identificador, string $email, array $direccion, GpUsuario $user): GpClienteSucursal;
    public function formPayloadNewClientSync(array $data): array;
    public function formPayloadClientBranchesSync(int $clienteId, string $ruc, string $descripcion,  array $data): array;
}
