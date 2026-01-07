<?php

namespace App\Services\Venta;

use App\Models\GpCliente;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use Illuminate\Database\Eloquent\Builder;

class ClientQueryService implements ClientQueryServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }

    public function getAllClients(string $term, ?string $type = null)
    {
        $searchTerm = '%' . str_replace(' ', '%', $term) . '%';

        return GpCliente::query()
            ->select('gp_clientes.*')
            ->where('CLI_RUC', '!=', '9999999999999')
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('CLI_RUC', 'LIKE', $searchTerm)
                    ->orWhere('CLI_RAZON_SOCIAL', 'LIKE', $searchTerm)
                    ->orWhere('CLI_NOMBRE_CORTO', 'LIKE', $searchTerm)
                    ->orWhere('CLI_CORREO_ELECT_SRI', 'LIKE', $searchTerm);
            })
            ->when($type !== 'cobranza', function (Builder $query) {
                $query->where('CLI_SUSPENDIDO', '!=', 'si');
            })
            ->with([
                'gpvendedor:id,VEND_ID,VEND_NOMBRES,VEND_APELLIDOS',
                'sucursales' => function ($query) {
                    $query->with('vendedor:VEND_ID,VEND_NOMBRES,VEND_APELLIDOS')
                        ->orderBy('CLI_SUC_ID_DIRECCION', 'DESC');
                }
            ])->get();
    }
}
