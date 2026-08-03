<?php
namespace App\Services\Builders\Cobranzas\CobrosFacturas\Contracts;

use App\Models\Cobranzas\GpCobro;
use App\Models\GpCaja;
use App\Models\GpUsuario;

interface CollectionRegistrationBuilderInterface
{
     public function createHeaderCollection(array $collectionData, int $userBoxedId, GpCaja $boxed): GpCobro;
}
