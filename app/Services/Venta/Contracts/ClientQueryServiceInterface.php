<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface ClientQueryServiceInterface
{


    // Define los métodos que debe implementar el servicio

    public function getAllClients(string $term, ?string $type = null);
    public function getAvailabeCreditCustomer(string $ruc): array;
    public function getCustomerBranches(string $ruc): mixed;

    public function storeNewAddressClient(array $data, GpUsuario $user);
}
