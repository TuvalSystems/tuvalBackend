<?php
namespace App\Services\Cobranzas\Contracts;

interface CollectionUnapplicationServiceInterface
{
    // Define los métodos

     public function fetchAppliedCollections(array $filters, int $perPage);
}
