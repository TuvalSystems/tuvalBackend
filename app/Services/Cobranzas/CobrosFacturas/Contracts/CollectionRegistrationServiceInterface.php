<?php

namespace App\Services\Cobranzas\CobrosFacturas\Contracts;

use App\Models\Cobranzas\Cobros\GpCobro;
use App\Models\Admin\Usuarios\GpUsuario;

interface CollectionRegistrationServiceInterface
{
    // Define los métodos
    public function registerCollection(array $collectionData, GpUsuario $user): GpCobro;
    public function reSendCollectionPendings(): void;
}
