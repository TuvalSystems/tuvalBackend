<?php

namespace App\Services\Pedidos\Contracts;

interface OrderServiceInterface
{
    public function getOrderInfo($id);
    public function generateInvoiceRide($id);
    public function generateInvoiceXml($id);
    public function storeOrder($order, $user);


    public function verifyOrderDispatchOrItemLRecived($orderId);
    public function detailOrderById($data);

    //volver a facturar
    public function checkOutValidateDispatchReInvoice($devolucion);

    public function getLisOrdersWithoutCoordinated();

    //recuperar pedidos

    public function restoreOrder($data, $user);


    /** facturar pedido */
    public function generateInvoiceOrder($orderId);

    /** funcion de copiar pedido  */

    public function copyOrder($data, $user);

    /** pruebas */
    public function buscarItemlTest($item);


    public function generateOrderDispatch($id);


    public function recalculateOrder($order);

    /**
     *  funcion de facturar siguiente mes de sincronizar
     *
     */
    public function generateNextMonthInvoice();

    /** funcion para la actualizacion de un pedido y el recalculo del mismo  */

    public function updateOrderAndRecalculate($orderId);

    /*** proceso de facturar existente  */

    public function checkExistence($data);
    public function generateInvoiceFromExistingOrder($data, $detalle, $user);

    public function syncPurchaseOrderAndNoteByOrder(int $id, ?string $newOrd, ?string $newNote);
     public function updateOrderReservationDate(int $orderId, string $newDate);


     /** listas de pedidos que nacen de una reserva */

    public function fetchOrderFromReservations($perPage = 10, $search, $sortBy = 'gp.created_at', $sortOrder = 'desc', $ptoVtaId);

    public function updateOrderAndBlockDispach($id, $pendingDispach);
}
