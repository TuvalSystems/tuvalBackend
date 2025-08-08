<?php

namespace App\Services\Reposiciones\Contracts;


interface StockWarehouseServiceInterface
{
    public function getWarehouseNotConsult($data);

    public function newFormatStock($stockResults,  $articulo);
    public function stockReservado(string $articulo, string $bodega, ?int $pedidoId = null);
    /**
     *  funciones en prueba para remplazo de newFormatStock y stockReservado
     */
    public function computeAvailableStockForArticle(array $stockResults, array $articulo);
    public function getReservedStockByWarehouses(string $articulo, array  $bodegas, ?int $pedidoId = null);

    /**
     * Esta funcion especifica para consulta stock y consulta otros stock
     */
    public function computeAvailableStockForArticleConsult(array $stockResults, array $articulo);
     /**fin de funciones en prueba para remplazo  */
    public function formatResultStocks($stockResults);

    public function getWarehouse($data);

    public function checkArticuloDisponible($articulo);

    public function getFirstAvailablePurchaseArticle($articulo, $excludeItemsL);

    public function formatStockDisponibleApprovalCreditOrComercial($stockFormatted, $articulo, $orderId);

    /** funcion para el formateo de stock para almacenaral momento de guardar un pedido */
    public function formatResultStockStoreOrder(&$stock, $article, $orderId);
}
