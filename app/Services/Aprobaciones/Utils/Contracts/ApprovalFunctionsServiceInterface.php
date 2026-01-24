<?php

namespace App\Services\Aprobaciones\Utils\Contracts;

use App\Models\GpDatosItemsL;

interface ApprovalFunctionsServiceInterface
{
    public function requiredApprovalResponse(string $motivo);
    public function requiredNotApprovalResponse(string $motivo);
    public function finalizeApprovalReasons(array $reasons);

    public function getTypeArticleLQuotationOrOrder(string $codArticulo): ?GpDatosItemsL;

     public function getNivelFormaPago($columna, $codigo);
     public function getPorcentilDiscount($columna, $codigo);
      public function getPorcentilDiscountCommercialCliente($ruc): float;
}
