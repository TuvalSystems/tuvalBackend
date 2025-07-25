<?php

namespace App\Services\Reposiciones\Contracts;


interface StockWarehouseServiceInterface
{
    public function getWarehouseNotConsult($data);
    public function newFormatStock($stockResults,  $articulo);
    public function stockReservado(string $articulo, string $bodega, ?int $pedidoId = null);

    public function formatResultStocks($stockResults);

    public function getWarehouse($data);

    public function checkArticuloDisponible($articulo);

    public function getFirstAvailablePurchaseArticle($articulo);

    public function formatStockDisponibleApprovalCreditOrComercial($stockFormatted, $articulo, $orderId);

    /** funcion para el formateo de stock para almacenaral momento de guardar un pedido */
    public function formatResultStockStoreOrder(&$stock, $article, $orderId);
}
