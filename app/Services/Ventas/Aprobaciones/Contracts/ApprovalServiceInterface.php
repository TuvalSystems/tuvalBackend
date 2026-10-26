<?php
namespace App\Services\Ventas\Aprobaciones\Contracts;


interface ApprovalServiceInterface
{
    public function approveQuoteReservation(array $data);
    public function checkOutApprovalCommercialReservedOrder(array $data);
}
