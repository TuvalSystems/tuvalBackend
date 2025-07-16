<?php

namespace App\Services\Venta\Utils\Contracts;

interface OrderAndQuotationServiceInterface
{
    public function getDetailQuotationOrOrder($data, $encera = false, $copy = false);

}
