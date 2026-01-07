<?php

namespace App\Services\Venta\Contracts;

interface ClientQueryServiceInterface
{


    // Define los métodos que debe implementar el servicio

      public function getAllClients( String $term, ?String $type = null);
       public function getAvailabeCreditCustomer(string $ruc): array;
       public function getCustomerBranches(string $ruc): mixed;
}
