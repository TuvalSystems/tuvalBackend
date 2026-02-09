<?php

namespace App\Services\Bodegas\Contracts;



interface DashboardServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function listDispatchOrdersByPtoVta(string $ptoVta): array;
    public function countItemServicesDisptach($ptoVta): array;
}
