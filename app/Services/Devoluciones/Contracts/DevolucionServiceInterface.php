<?php

namespace App\Services\Devoluciones\Contracts;

interface DevolucionServiceInterface
{

    public function processDevolucion( $devolucion);
    public function validateInvoiceAnulation($pedido);
    public function countsReturnsByOrders($pedido, $devolucion);

    public function anularDevolucion($pedido, $devolucion);

    public function executeReturn($data);

    public function validateItemLDetailInvoice( $devolucion);

    public function getReturnsDetailDevolucion($devolucion);

}
