<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Collection;

interface ClientQueryServiceInterface
{


    // Define los métodos que debe implementar el servicio

    public function getAllClients(string $term, ?string $type = null);
    public function getAvailabeCreditCustomer(string $ruc): array;
    public function getCustomerBranches(string $ruc, ?string $type = null): mixed;

    public function storeNewAddressClient(array $data, GpUsuario $user);
    public function createRegisterBranchClient(GpClienteSucursal $data, int $mode, ?string $userIngreso = null, ?string $fechaCreacion = null,  ?string $userModificacion = null, ?string $fechaModificacion = null, bool $autoRetry = true);
    public function getListTypeDni();
    public function getAllSegments();
    public function getSubSegmentBySegment(int $coAtrPadre);
    public function validateHasExistenceDocument(string $document): bool;
    public function validateHasExistenceProspect(string $document): bool;
    public function getListGrupCustomers():Collection;
}
