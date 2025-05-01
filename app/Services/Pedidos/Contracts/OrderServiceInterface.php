<?php

namespace App\Services\Pedidos\Contracts;

interface OrderServiceInterface
{
        public function getOrderInfo($id);
        public function generateInvoiceRide($id);
        public function generateInvoiceXml($id);
        public function storeOrder($order);
}
