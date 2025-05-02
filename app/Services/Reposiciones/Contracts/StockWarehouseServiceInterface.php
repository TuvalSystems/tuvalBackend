<?php

namespace App\Services\Reposiciones\Contracts;


interface StockWarehouseServiceInterface
{
    public function getWarehouseNotConsult($data);
    public function newFormatStock($stockResults,  $articulo);
    public function stockReservado(string $articulo, string $bodega, ?int $pedidoId = null);

    public function formatResultStocks($stockResults);

    public function getWarehouse($data);

}
