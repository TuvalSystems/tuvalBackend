<?php
namespace App\Services\Aprobaciones\Contracts;

use App\Models\GpUsuario;

interface ApprovalServiceInterface
{

    public function approveQuoteReservation(array $data);
    public function checkoutApprovalCredit(string $condId, float $total, string $ruc);

    public function checkoutApprovalDevoluciones(array $data);


    public function checkOutApprovalCommercialReservedOrder(array $data);

    /**
     *
     * funciones para las aprobaciones crediticias
     *
     * */

    public function getOrderApprovalData(array $data);

    //function cuando cumple las condiciones para la aprobación
    public function approveCreditOrder(array $data, GpUsuario $user);
    // cuando se rechaza un pedido - credito
    public function denyCreditApproval(array $data, GpUsuario $user);
    // cuando se rechaza de forma automatica en caso de que el stock sea insuficiente
    public function rejectCreditOrderForInsufficientStock(array $data, GpUsuario $user);

    /** funciones para aprobacion comercial  */

    public function approveComercialOrder(array $data, GpUsuario $user);
    public function denyApproveComercialOrderForInsufficientStock(array $data, GpUsuario $user);
    public function denyApproveComercialOrder(array $data, GpUsuario $user);

    /** funcion para obtener el estado de cuenta de un cliente */

    public function accountStatusClient(string $ruc);


    public function denyQuotationBeenWaiting(int $id, GpUsuario $user);
    public function denyOrderBeenWaiting(int $id,  GpUsuario $user);

    /**  aprobacicon comercial de cotizacion */
    public function approveComercialQuote(int $id, GpUsuario $user);
}
