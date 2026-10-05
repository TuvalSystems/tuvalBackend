<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;

interface ClientBuilderInterface
{

    public function formPayloadNewAddress(array $data, GpCliente $cliente, array $direccion, string $email): array;
    public function formPayloadNewSucursal(array $data, GpCliente $cliente, ?string $identificador, string $email, array $direccion, GpUsuario $user): GpClienteSucursal;
    public function formPayloadNewClientSync(array $data, ?int $regionId, ?int $provinciaId, ?int $cantonId): array;
    public function formPayloadClientBranchesSync(int $clienteId, string $ruc, string $descripcion, array $data, ?int $regionId, ?int $provinciaId, ?int $cantonId): array;

    /** nuevo proceso de creacion de cliente */
    public function formPayloadStoreClient(array $data, GpUsuario $user): GpCliente;
    public function formPayloadStoreAddressByClient(GpCliente $client, GpUsuario $user, array $data, ?string $identificador = null): GpClienteSucursal;

    /** nuevo proceso para envio de clientes a api */
    public function formPayloadSenderClientGp(GpCliente $client): array;
    public function formPayloadSenderClientBranchToGp(GpClienteSucursal $branch): array;
}
