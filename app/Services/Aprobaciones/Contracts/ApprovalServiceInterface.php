<?php
namespace App\Services\Aprobaciones\Contracts;


interface ApprovalServiceInterface
{

    public function approveQuoteReservation(array $data);
    public function checkoutApprovalCredit(string $condId, float $total, string $ruc);

    public function checkOutApprovalCommercialReservedOrder(array $data);


}
