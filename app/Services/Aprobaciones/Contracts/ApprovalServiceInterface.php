<?php
namespace App\Services\Aprobaciones\Contracts;

interface ApprovalServiceInterface
{


    public function checkOutApprovalCommercial($data, $type);
    public function checkoutApprovalCredit($data);

    public function checkoutApprovalDevoluciones($data);


    public function checkOutApprovalCommercialReservedOrder($data);

    /**
     *
     * funciones para las aprobaciones crediticias
     *
     * */

    public function getOrderApprovalData($data);

    //function cuando cumple las condiciones para la aprobación
    public function approveCreditOrder($data);
    // cuando se rechaza un pedido - credito
    public function denyCreditApproval ($data);
    // cuando se rechaza de forma automatica en caso de que el stock sea insuficiente
    public function rejectCreditOrderForInsufficientStock ($data);

    /** funciones para aprobacion comercial  */

    public function approveComercialOrder($data);
    public function denyApproveComercialOrderForInsufficientStock($data);
    public function denyApproveComercialOrder($data);

}
