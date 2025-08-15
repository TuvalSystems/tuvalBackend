<?php

namespace App\Services\SqlServer\Cobranza\Contracts;

interface CreditValidationServiceInterface
{
    // Define los métodos que debe implementar el servicio

     public function consultCreditValidate(string $idCliente, int $modo);
}
