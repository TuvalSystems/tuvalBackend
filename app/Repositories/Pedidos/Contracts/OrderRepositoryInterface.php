<?php

namespace App\Repositories\Pedidos\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface OrderRepositoryInterface
{
    public function getAllDetailsOrderById($id);

    public function getSuppliersByOrderAndItem($pedidoId, $itemId);
    public function getPurchaseOrdersBySupplier($proveedorId);

    public function getStockByItemAndWarehouse(array $itemId, string $warehouse);

    public function getPurchaseOrdersByOrderNumber(string $orderNumber);

    public function getTransferOrdersBySale(int $orderId);
    public function getShipmentGuidesByTransfer(object $transfer);
    public function getDispatchOrder(int $orderId);
    public function getShipmentGuides(string $orderCode);
    public function getReturnsByOrder(int $orderId);

    /** facturacion de siguiente mes */
    public function queryOrdersToInvoiceNextMonth($year, $mes);

    /** functiones  para el proceso de facturar existentes */


    public function getStockOrderDetailForCheckExistence($warehouseId, $detailOrder);



    /** funcion para obtener todos los pedidos en estado de espera para facturar */
    public function queryInvoiceReservedOrdersForDate($date);

    public function queryHasGuideDispatched($ids): bool;



    /** para facturacion   */
    public function getDataCheckoutTraslate(int $orderId, String $warehouse): bool;
    public function getDetailForTraslate(int $orderId, String $warehouse);


    /** query para lista de pedidos pendientes de aprobacion comercial */
    public function fetchPendingOrders(int $ptoVta): Builder;
}
