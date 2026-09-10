<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpPedido;
use App\Models\GpUsuario;

interface OrderServiceInterface
{
    public function getOrderInfo(int $id);
    public function generateInvoiceRide(int $id);
    public function generateInvoiceXml(int $id);


    public function verifyOrderDispatchOrItemLRecived(int $orderId);
    public function detailOrderById(array $data, GpUsuario $user);
     public function getOrderDetailStatement(int $id, GpUsuario $user): array;


    public function getLisOrdersWithoutCoordinated();

    //recuperar pedidos

     public function loadOrderForCopy(int $orderId, GpUsuario $user): array ;

    /** facturar pedido */
    public function generateInvoiceOrder(int $orderId);

    /** funcion de copiar pedido  */

    public function copyQuoteOrOrder(int $rowId, int  $sucursalId, int $clienteId, string $type, GpUsuario $user);

    /** pruebas */
    public function buscarItemlTest($item);


    public function generateOrderDispatch(int $id);



    /**
     *  funcion de facturar siguiente mes de sincronizar
     *
     */
    public function generateNextMonthInvoice();

    /** funcion para la actualizacion de un pedido y el recalculo del mismo  */

    public function updateOrderAndRecalculate(int $orderId);

    /*** proceso de facturar existente  */

    public function checkExistence(array $data): array;
    public function generateInvoiceFromExistingOrder($data, $detalle, $user);

    public function syncPurchaseOrderAndNoteByOrder(int $id, ?string $newOrd, ?string $newNote);
    public function updateOrderReservationDate(int $orderId, string $newDate);


    /** listas de pedidos que nacen de una reserva */

    public function fetchOrderFromReservations($perPage = 10, $search, $sortBy = 'gp.created_at', $sortOrder = 'desc', $ptoVtaId);

    public function updateOrderAndBlockDispach($id, $pendingDispach, GpUsuario $user);

    public function hasDeliveryNoteAjusted($id): bool;


    public function valdidateOrdersProviders(GpPedido $order);

     public function createTransferVtaOrders(string $orderNumber);


     public function cancelOrder(int $orderId, GpUsuario $user) : GpPedido;
}
