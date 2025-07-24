<?php

namespace App\Services\Pedidos\Contracts;

interface OrderServiceInterface
{
        public function getOrderInfo($id);
        public function generateInvoiceRide($id);
        public function generateInvoiceXml($id);
        public function storeOrder($order, $user);

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
    }
