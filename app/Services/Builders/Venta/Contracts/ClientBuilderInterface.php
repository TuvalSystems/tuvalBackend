<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;

interface ClientBuilderInterface
{

    public function formPayloadNewAddress(array $data, GpCliente $cliente, $direccion, $email): array;
    public function formPayloadNewSucursal(array $data, GpCliente $cliente, $identificador, $email, $direccion, GpUsuario $user): GpClienteSucursal;
    public function formPayloadNewClientSync(array $data): array;
    public function formPayloadClientBranchesSync(int $clienteId, string $ruc, string $descripcion,  array $data);
}
