<?php
namespace App\Services\Cobranzas\Aplicaciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface CustomerPaymentApplicationServiceInterface
{
    // Define los métodos
     public function validateIdentifyPaymentInvoices(array $payload, GpUsuario $user);

     public function updatePendingApplicationDocumentBalances(int $clientId, array $documents);


}
