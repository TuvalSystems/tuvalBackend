<?php

namespace App\Services\Devoluciones\Aprobaciones\Contracts;

interface ApprovalDevolutionServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function checkoutApprovalDevoluciones(array $data);

}
