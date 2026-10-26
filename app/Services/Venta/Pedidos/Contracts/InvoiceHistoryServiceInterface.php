<?php
namespace App\Services\Venta\Pedidos\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface InvoiceHistoryServiceInterface
{
    // Define los métodos
    public function fetchInvoiceHistory(GpUsuario $user, int $clientId, int $pvId, string $pvCode, array $filters ) : LengthAwarePaginator;
}
