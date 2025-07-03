<?php
namespace App\Services\Aprobaciones\Contracts;

interface ApprovalServiceInterface
{


    public function checkoutApprovalComercial($data, $order = false);

    public function checkoutApprovalDevoluciones($data);

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

}
