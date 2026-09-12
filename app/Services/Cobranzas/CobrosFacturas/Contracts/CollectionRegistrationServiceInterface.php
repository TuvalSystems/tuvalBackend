<?php

namespace App\Services\Cobranzas\CobrosFacturas\Contracts;

use App\Models\Cobranzas\GpCobro;
use App\Models\GpUsuario;

interface CollectionRegistrationServiceInterface
{
    // Define los métodos
    public function registerCollection(array $collectionData, GpUsuario $user): GpCobro;
    public function reSendCollectionPendings(): void;
}
