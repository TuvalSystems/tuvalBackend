<?php

namespace App\Repositories\Devoluciones\Contracts;

interface DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided($pedido);
    public function validateInvoiceAnulation($pedido);
    public function countsReturnsByOrders($pedido, $devolucion);
    public function validateItemLDetailInvoice($devolucion);
}
