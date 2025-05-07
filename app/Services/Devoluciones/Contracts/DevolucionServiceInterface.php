<?php

namespace App\Services\Devoluciones\Contracts;

interface DevolucionServiceInterface
{

    public function processDevolucion( $devolucion);
    public function validateInvoiceAnulation($pedido);

    public function anularDevolucion($pedido, $devolucion);

}
