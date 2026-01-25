<?php

namespace App\Services\Venta\Utils\Contracts;

interface OrderAndQuotationServiceInterface
{
    public function getDetailQuotationOrOrder($data, $cliente, $type, $encera = false, $copy = false);


    public function createRegisterCopy($dataId, $user, $typeCopy, $typeRegisterCopy);

    public function getSegmentoCliente($ruc);

    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId, $quotationId = null);



    public function calculateDiscountFraction(float $originalPrice, float $currentPrice);
    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount);



    public function getAllImagensOperations($quotationId);
}
