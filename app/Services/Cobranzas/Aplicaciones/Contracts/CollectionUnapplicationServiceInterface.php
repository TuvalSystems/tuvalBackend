<?php
namespace App\Services\Cobranzas\Aplicaciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface CollectionUnapplicationServiceInterface
{
    // Define los métodos

     public function fetchAppliedCollections(array $filters);
      public function getAllDetailCollection(string $ruc, string $factura, GpUsuario $user);
      public function processUpdateUnapplicationItems(array $items, GpUsuario $user);
}
