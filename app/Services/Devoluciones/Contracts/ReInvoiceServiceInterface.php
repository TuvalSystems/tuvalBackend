<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\Devoluciones\GpDevoluciones;

interface ReInvoiceServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function execute(GpDevoluciones $devolucion);
}