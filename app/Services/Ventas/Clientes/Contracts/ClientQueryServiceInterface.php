<?php

namespace App\Services\Ventas\Clientes\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use Illuminate\Database\Eloquent\Collection;

interface ClientQueryServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getAllClients(string $term, ?string $type = null);
    public function getAvailabeCreditCustomer(string $ruc): array;
    public function getCustomerBranches(string $ruc, ?string $type = null): mixed;
    public function getListTypeDni();
    public function getAllSegments();
    public function getSubSegmentBySegment(int $coAtrPadre);
    public function validateHasExistenceDocument(string $document): bool;
    public function validateHasExistenceProspect(string $document): bool;
    public function getListGrupCustomers():Collection;
}
