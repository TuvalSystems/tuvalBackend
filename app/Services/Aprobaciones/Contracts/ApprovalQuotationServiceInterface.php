<?php

namespace App\Services\Aprobaciones\Contracts;

interface ApprovalQuotationServiceInterface
{
    // Define los métodos que debe implementar el servicio

     public function approveQuotation(array $data): array;
}
