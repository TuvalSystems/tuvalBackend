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
    public function approveCreditOrder($data, $user);
    // cuando se rechaza un pedido - credito
    public function denyCreditApproval ($data, $user);
    // cuando se rechaza de forma automatica en caso de que el stock sea insuficiente
    public function rejectCreditOrderForInsufficientStock ($data, $user);

    /** funciones para aprobacion comercial  */

    public function approveComercialOrder($data, $user);
    public function denyApproveComercialOrderForInsufficientStock($data, $user);
    public function denyApproveComercialOrder($data, $user);

    /** funcion para obtener el estado de cuenta de un cliente */

    public function accountStatusClient($ruc);


    public function denyQuotationBeenWaiting(int $id);
    public function denyOrderBeenWaiting(int $id);
}
