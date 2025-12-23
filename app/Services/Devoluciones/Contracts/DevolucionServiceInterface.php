<?php

namespace App\Services\Devoluciones\Contracts;

interface DevolucionServiceInterface
{
    public function validateInvoiceNotDispatchOrInProcess($pedido);
    public function countsReturnsByOrders($pedido, $devolucion);

    public function handleDevolutionTransaction($data);
    // public function anularDevolucion($pedido, $devolucion);

    public function canBeAnulledByLimitDate($pedido);

    public function executeReturn($data);

    public function validateItemLDetailInvoice( $devolucion);

    public function getReturnsDetailDevolucion($devolucion);

    public function validateItemPartialDispatch($devolucion);

    public function getDetailOrderReturnById($order);

    public function printReturn($devolucion_id);

    /** funciones a ejecutar en caso de que no se genere la nota de credito o el ajuste  */
    public function generarNotaCreditoDevolucionesFallidas($id);
    public function generarAjusteDevolucionesVentaFallidas($id);

    public function  responseCreditNoteDevolution($devolucionId);

}
