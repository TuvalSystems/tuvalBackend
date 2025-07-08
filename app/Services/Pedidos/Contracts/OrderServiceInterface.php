<?php

namespace App\Services\Pedidos\Contracts;

interface OrderServiceInterface
{
        public function getOrderInfo($id);
        public function generateInvoiceRide($id);
        public function generateInvoiceXml($id);
        public function storeOrder($order);

        public function detailOrderById($data);

        //volver a facturar
        public function checkOutValidateDispatchReInvoice($devolucion);

        public function getLisOrdersWithoutCoordinated();

        //recuperar pedidos

        public function restoreOrders($articulos, $bodegas);

        /** generar factura siguiente mes */
        public function generateNextMonthInvoice();

     /** facturar pedido */
        public function generateInvoiceOrder($orderId);
    }
