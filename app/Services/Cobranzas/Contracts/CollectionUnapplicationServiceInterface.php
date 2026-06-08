<?php
namespace App\Services\Cobranzas\Contracts;

use App\Models\GpUsuario;

interface CollectionUnapplicationServiceInterface
{
    // Define los métodos

     public function fetchAppliedCollections(array $filters);
      public function getAllDetailCollection(string $ruc, string $factura);
      public function processUpdateUnapplicationItems(array $items, GpUsuario $user);
}
