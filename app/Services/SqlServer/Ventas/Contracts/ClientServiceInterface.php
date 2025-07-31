<?php

namespace App\Services\SqlServer\Ventas\Contracts;

interface ClientServiceInterface
{
    // Define los métodos que debe implementar el servicio

     public function validateClientforRucSegment($ruc);
}
