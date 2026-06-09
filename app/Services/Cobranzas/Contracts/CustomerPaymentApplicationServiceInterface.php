<?php
namespace App\Services\Cobranzas\Contracts;

use App\Models\GpUsuario;

interface CustomerPaymentApplicationServiceInterface
{
    // Define los métodos
     public function validateIdentifyPaymentInvoices(array $payload);
     public function storeFromValidationResult(array $payload , GpUsuario $user);
     public function cleanInternalHistory(array $payload);

}
