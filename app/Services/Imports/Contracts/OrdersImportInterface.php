<?php

namespace App\Services\Imports\Contracts;

interface OrdersImportInterface
{
    // Define los métodos que debe implementar el servicio
    public function updateDataSqlServerOrdersAndDetail(string $fechaIni, string $fechaFin);
}
