<?php

namespace App\Services\Venta\Utils\Contracts;

interface OrderAndQuotationServiceInterface
{
    public function getDetailQuotationOrOrder($cliente,$data, $encera = false, $copy = false , $type);

    public function clearTempDataQuotation($sucursalId, $PTO_VTA_ID, $clienteId, $userId);

    public function createRegisterCopy($dataId, $user, $typeCopy, $typeRegisterCopy);

    public function getSegmentoCliente($ruc);

    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId);

}
