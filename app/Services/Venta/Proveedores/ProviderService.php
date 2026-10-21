<?php

namespace App\Services\Venta\Proveedores;

use App\Models\GpUsuario;
use App\Models\Ventas\Proveedores\GpProveedor;
use App\Services\Venta\Proveedores\Contracts\ProviderServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            'id'                => 'prv.id',
            'ruc'               => 'prv.PROV_RUC',
            'razon_social'      => 'prv.PROV_RAZON_SOCIAL',
            'id_pricipal'       => 'prv.PROV_ID_DIRECCION',
            'estado'            => 'prv.PROV_ESTADO',
            'es_transportista'  => 'prv.es_transportista'
        ];

        $orderColumn = data_get($allowedSorts, $sortField, 'prv.created_at');

        $query = GpProveedor::query()
            ->from('gp_proveedors as prv')
            ->select([
                'prv.id',
                'prv.PROV_RUC as ruc',
                'prv.PROV_RAZON_SOCIAL as razon_social',
                'prv.PROV_CLASE as clase',
                'prv.PROV_ID_DIRECCION as id_principal',
                'prv.PROV_CONTACTO as contacto',
                'prv.PROV_DIRECCION_1 as direccion',
                'prv.es_transportista',
                'prv.fle_usuario_id',
                'prv.fle_fecha',
                'prv.PROV_ESTADO as estado',
                'prv.created_at'
            ]);

        if ($search) {
            $query->where('prv.PROV_RUC', 'like', "%{$search}%")
                ->orWhere('prv.PROV_RAZON_SOCIAL', 'like', "%{$search}%")
                ->orWhere('prv.PROV_CLASE', 'like', "%{$search}%");
        }

        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }



    public function updateCarrierStatus(int $id, GpUsuario $user): void
    {
        DB::transaction(function () use ($id, $user) {
            $prov = GpProveedor::whereKey($id)->lockForUpdate()->firstOrFail();
            $prov->es_transportista = !$prov->es_transportista;
            if ($prov->es_transportista) {
                $prov->fle_usuario_id = $user->id;
                $prov->fle_fecha = now();
            }
            $prov->usuario_modifica_id = $user->id;
            $prov->fecha_modificacion = now();
            $prov->save();
        });
    }

    public function updateStatus(int $id, GpUsuario $user): void
    {
        $prov                       = GpProveedor::find($id);
        $prov->PROV_ESTADO          = $prov->PROV_ESTADO === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $prov->usuario_modifica_id  = $user->id;
        $prov->fecha_modificacion   = now();
        $prov->save();
    }


    public function listProviderTypeCarrier(int $pvId): Collection
    {
        return GpProveedor::whereHas('puntosVenta', function ($q) use ($pvId) {
            $q->where('gp_punto_ventas.id', $pvId);
        })
            ->select([
                'id',
                'PROV_RUC as ruc',
                'PROV_RAZON_SOCIAL as razon_social'
            ])
            ->where('PROV_ESTADO', 'ACTIVO')
            ->where('es_transportista', true)
            ->get();
    }


    public function getDetailProvider(int $id): GpProveedor
    {
        return GpProveedor::with([
            'puntosVenta' => function ($query) {
                $query
                    ->select([
                        'gp_punto_ventas.id',
                        'gp_punto_ventas.PTO_VTA_ID as codigo',
                        'gp_punto_ventas.PTO_VTA_DESCRIPCION as descripcion',
                    ]);
            }
        ])->findOrFail($id);
    }


    public function getAllProviders(): Collection
    {
        return GpProveedor::select(['id', 'PROV_RAZON_SOCIAL as razon_social'])
            ->where('PROV_CLASE', '!=', 'PR-EXT')
            ->where('PROV_ESTADO', 'ACTIVO')
            ->orderBy('PROV_RAZON_SOCIAL')->get();
    }


    public function syncPotinSalesByProvider(int $id, array $pvIds): GpProveedor
    {
        return DB::transaction(function () use ($id, $pvIds) {
            $prov = GpProveedor::find($id);
            $prov->puntosVenta()->sync($pvIds);
            return $prov;
        });
    }
}
