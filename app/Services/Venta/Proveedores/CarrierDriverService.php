<?php

namespace App\Services\Venta\Proveedores;

use App\Models\GpUsuario;
use App\Models\Ventas\Proveedores\ChoferTransportista;
use App\Services\Venta\Proveedores\Contracts\CarrierDriverServiceInterface;
use App\Traits\CleansPayloadData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CarrierDriverService implements CarrierDriverServiceInterface
{
    use CleansPayloadData;
    public function __construct()
    {
        // Inyecta dependencias
    }

    public function listCarrierDrivers(array $filters): LengthAwarePaginator
    {
        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        $pvId       = data_get($filters, 'pv_id');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [
            'id'                     => 'cht.id',
            'identificacion'         => 'cht.identificacion',
            'ruc_proveedor'          => 'prv.PROV_RUC',
            'razon_social_proveedor' => 'prv.PROV_RAZON_SOCIAL',
            'nombre'                 => 'cht.nombre',
            'placa'                  => 'cht.placa',
            'estado'                 => 'cht.estado',
            'created_at'             => 'cht.created_at'
        ];
        $orderColumn = data_get($allowedSorts, $sortField, 'cht.created_at');

        $query  = ChoferTransportista::query()
            ->from('chofer_transportistas as cht')
            ->select([
                'cht.id',
                'cht.proveedor_id',
                'prv.PROV_RUC as ruc_proveedor',
                'prv.PROV_RAZON_SOCIAL as razon_social_proveedor',
                'tp.nombre as tipo_identificacion',
                'cht.identificacion',
                'cht.nombre',
                'cht.placa',
                'cht.estado',
                'cht.created_at',
            ])
            ->join('gp_proveedors as prv', 'prv.id', '=', 'cht.proveedor_id')
            ->join('tipo_identificacions as tp', 'tp.id', '=', 'cht.tipo_identificacion_id');

        if ($pvId) {
            $query->whereExists(function ($query) use ($pvId) {
                $query->selectRaw('1')
                    ->from('proveedores_punto_ventas as ppv')
                    ->whereColumn('ppv.prov_id', 'prv.id')
                    ->where('ppv.pv_id', $pvId);
            });
        }

        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('prv.PROV_RUC', 'like', "%{$search}%")
                    ->orWhere('prv.PROV_RAZON_SOCIAL', 'like', "%{$search}%")
                    ->orWhere('cht.nombre', 'like', "%{$search}%")
                    ->orWhere('cht.placa', 'like', "%{$search}%");
            });
        }
        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }


    public function createRegisterCarrier(array $data, GpUsuario $user): ChoferTransportista
    {
        return DB::transaction(function () use ($data, $user) {
            $cht = new ChoferTransportista();
            $cht->proveedor_id              = $this->clean($data, 'proveedor_id');
            $cht->nombre                    = $this->clean($data, 'nombre');
            $cht->identificacion            = $this->clean($data, 'identificacion');
            $cht->tipo_identificacion_id    = $this->clean($data, 'tipo_identificacion_id');
            $cht->placa                     = $this->clean($data, 'placa');
            $cht->usuario_ingreso_id        = $user->id;
            $cht->fecha_ingreso             = now();
            $cht->save();
            return $cht;
        });
    }

    public function getDetailCarrierDriver(int $id): ChoferTransportista
    {
        return ChoferTransportista::where('id', $id)->first();
    }

    public function updateRegisterCarrier(int $id, array $data, GpUsuario $user): ChoferTransportista
    {
        return DB::transaction(function () use ($id, $data, $user) {
            $cht = ChoferTransportista::find($id);
            $cht->proveedor_id              = $this->clean($data, 'proveedor_id');
            $cht->nombre                    = $this->clean($data, 'nombre');
            $cht->identificacion            = $this->clean($data, 'identificacion');
            $cht->tipo_identificacion_id    = $this->clean($data, 'tipo_identificacion_id');
            $cht->placa                     = $this->clean($data, 'placa');
            $cht->usuario_modifica_id       = $user->id;
            $cht->fecha_modificacion        = now();
            $cht->save();


            return $cht;
        });
    }

    public function updateStatusCarrier(int $id): ChoferTransportista
    {
        return DB::transaction(function () use ($id) {
            $cht         = ChoferTransportista::find($id);
            $cht->estado = $cht->estado  === true ? false : true;
            $cht->save();
            return $cht;
        });
    }

    public function validateCarrierIdentify(string $identify, ?int $excludeId = null): bool
    {
        return ChoferTransportista::where('identificacion', $identify)
            ->when(
                $excludeId,
                fn($query) => $query->where('id', '!=', $excludeId)
            )->exists();
    }


    public function getCarrierDriversByProviderAndSalesPoint(int $provId, int $pvId): Collection
    {
        return ChoferTransportista::whereHas('proveedor', function ($query) use ($provId, $pvId) {
            $query->where('gp_proveedors.id', $provId)
                ->whereHas('puntosVenta', function ($q) use ($pvId) {
                    $q->where('gp_punto_ventas.id', $pvId);
                });
        })
            ->with([
                'tipoIdentificacion:id,codigo'
            ])->get(['id', 'nombre', 'identificacion', 'tipo_identificacion_id', 'placa']);
    }
}
