<?php

namespace App\Services\Aprobaciones\Contracts;

interface ApprovalOrderServiceInterface
{

    public function approveOrder($data);
    public function checkoutApprovalCreditOrder($condId, $total, $ruc): string;
}
