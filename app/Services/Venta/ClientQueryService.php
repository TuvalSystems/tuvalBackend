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
            ->from('gp_clientes as gpc')
            ->select([
                'gpc.id',
                'gpc.CLI_RUC',
                'gpc.CLI_RAZON_SOCIAL',
                'gpc.CLI_NOMBRE_CORTO',
                'gpc.CLI_CONTACTO',
                'gpc.CLI_CIUDAD',
                'gpc.CLI_PROVINCIA',
                'gpc.CLI_TELEFONO_1',
                'gpc.CLI_CORREO_ELECT_SRI',
            ])
            ->where('CLI_RUC', '!=', '9999999999999')
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('gpc.CLI_RUC', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_RAZON_SOCIAL', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_NOMBRE_CORTO', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_CORREO_ELECT_SRI', 'LIKE', $searchTerm);
            })

            // ->when($type !== 'cobranza', function (Builder $query) {
            //     $query->where('CLI_SUSPENDIDO', '!=', 'si');
            // })
            // ->with([
            //     'gpvendedor:id,VEND_ID,VEND_NOMBRES,VEND_APELLIDOS',
            //     'sucursales' => function ($query) {
            //         $query->with('vendedor:VEND_ID,VEND_NOMBRES,VEND_APELLIDOS')
            //             ->orderBy('CLI_SUC_ID_DIRECCION', 'DESC');
            //     }
            // ])
            ->limit(120)
            ->get();
    }
}
