<?php

namespace App\Services\Venta\Proveedores;

use App\Models\GpUsuario;
use App\Models\Ventas\Proveedores\GpProveedor;
use App\Services\Venta\Proveedores\Contracts\ProviderServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProviderService implements ProviderServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }

    public function listProviders(array $filters, GpUsuario $user): LengthAwarePaginator
    {
        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [
            'id'           => 'prv.id',
            'ruc'          => 'prv.PROV_RUC',
            'razon_social' => 'prv.PROV_RAZON_SOCIAL',
            'id_pricipal'  => 'prv.PROV_ID_DIRECCION'
        ];

        $orderColumn = data_get($allowedSorts, $sortField, 'prv.created_at');

        $query = GpProveedor::query()
            ->from('gp_proveedores as prv')
            ->select([
                'prv.id',
                'prv.PROV_RUC as ruc',
                'prv.PROV_RAZON_SOCIALa as razon_social',
                'prv.PROV_CLASE as clase',
                'prv.PROV_ID_DIRECCION as id_principal',
                'prv.PROV_CONTACTO as contacto',
                'prv.PROV_DIRECCION as direccion',
                'prv.es_transportista',
                'prv.PROV_ESTADO as estado',
                'prv.created_at'
            ]);

        if ($search) {
            $query->where('prv.PROV_RUC', 'like', "%{$search}%")
                ->orWhere('prv.PROV_RAZON_SOCIA', 'like', "%{$search}%")
                ->orWhere('prv.PROV_CLASE', 'like', "%{$search}%");
        }

        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }



    public function updateCarrierStatus(int $id, GpUsuario $user): void
    {
        $prov                       = GpProveedor::find('id', $id);
        $prov->es_transportista     = $prov->es_transportista === true ? false : true;
        $prov->usuario_modifica_id  = $user->id;
        $prov->fecha_modificacion   = now();
        $prov->save();
    }

    public function updateStatus(int $id, GpUsuario $user): void
    {
        $prov                       = GpProveedor::find('id', $id);
        $prov->PROV_ESTADO          = $prov->PROV_ESTADO === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        $prov->usuario_modifica_id  = $user->id;
        $prov->fecha_modificacion   = now();
        $prov->save();
    }
}
