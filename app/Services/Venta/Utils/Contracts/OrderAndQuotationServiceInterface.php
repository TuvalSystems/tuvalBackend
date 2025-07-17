<?php

namespace App\Services\Venta\Utils\Contracts;

interface OrderAndQuotationServiceInterface
{
    public function getDetailQuotationOrOrder($data, $encera = false, $copy = false , $type);

    public function clearTempDataQuotation($sucursalId, $PTO_VTA_ID, $clienteId, $userId);

}
