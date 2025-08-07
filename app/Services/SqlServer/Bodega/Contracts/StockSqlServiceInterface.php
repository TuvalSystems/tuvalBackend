<?php

namespace App\Services\SqlServer\Bodega\Contracts;

interface StockSqlServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function consultStockWarehouseSql(array $params);
}
