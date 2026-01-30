<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;

interface ClientBuilderInterface
{

    public function formPayloadNewAddress(array $data, GpCliente $cliente, $direccion, $email): array ;
    public function formPayloadNewSucursal(array $data, GpCliente $cliente, $identificador, $email, $direccion): GpClienteSucursal ;
}
