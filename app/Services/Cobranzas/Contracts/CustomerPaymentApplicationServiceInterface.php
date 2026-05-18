<?php
namespace App\Services\Cobranzas\Contracts;

interface CustomerPaymentApplicationServiceInterface
{
    // Define los métodos
     public function validateIdentifyPaymentInvoices(array $payload);
}
