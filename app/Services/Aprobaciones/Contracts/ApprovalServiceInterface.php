<?php 
namespace App\Services\Aprobaciones\Contracts;

interface ApprovalServiceInterface
{
 

    public function checkoutApprovalComercial($data, $order = false);
    
}