<?php

namespace App\Services\Inventario\Despachos;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Inventario\Despachos\TipoDespacho;
use App\Services\Inventario\Despachos\Contracts\DispatchTypeServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DispatchTypeService implements DispatchTypeServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }


    public function list(GpUsuario $user, array $filters = []): LengthAwarePaginator
    {

        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        // $status     = data_get($filters, 'estado');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [
            'id'          => 'dpst.id',
            'descripcion' => 'dpst.descripcion'
        ];

        $orderColumn = data_get($allowedSorts, $sortField, 'dpst.created_at');

        $query = TipoDespacho::query()
            ->select([
                'dpst.id',
                'dpst.descripcion',
                'dpst.observacion',
                'dpst.estado',
                'dpst.responsable_pago',
                'dpst.created_at'
            ])
            ->from('tipo_despachos as dpst');
        if ($search) {
            $query->where('dpst.descripcion', 'like', "%{$search}%");
        }
        // $booleanStatus = filter_var(
        //     $status,
        //     FILTER_VALIDATE_BOOLEAN,
        //     FILTER_NULL_ON_FAILURE
        // );

        // if ($booleanStatus !== null) {
        //     $query->where('dpst.estado', $booleanStatus);
        // }
        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }


    public function create(array $data, GpUsuario $user)
    {
        return DB::transaction(function () use ($data, $user) {

            $dpsType = new TipoDespacho();

            $dpsType->descripcion        = data_get($data, 'descripcion');
            $dpsType->observacion        = data_get($data, 'observacion');
            $dpsType->responsable_pago   = data_get($data, 'responsable_pago');
            $dpsType->usuario_ingreso_id = $user->id;
            $dpsType->fecha_ingreso      = now();

            $dpsType->save();

            $dpsType->puntosVenta()->sync(
                data_get($data, 'punto_venta_ids', [])
            );

            return $dpsType->load('puntosVenta');
        });
    }


    public function getDetail(int $id): TipoDespacho
    {
        return TipoDespacho::with([
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


    public function update(int $id, array $data, GpUsuario $user)
    {
        return DB::transaction(function () use ($id, $data, $user) {

            $dpsType = TipoDespacho::findOrFail($id);
            $dpsType->descripcion          = data_get($data, 'descripcion');
            $dpsType->observacion          = data_get($data, 'observacion');
            $dpsType->responsable_pago     = data_get($data, 'responsable_pago');
            $dpsType->usuario_modifica_id  = $user->id;
            $dpsType->fecha_modificacion   = now();
            $dpsType->save();

            $dpsType->puntosVenta()->sync(
                data_get($data, 'punto_venta_ids', [])
            );

            return $dpsType->load('puntosVenta');
        });
    }


    public function activeOrInactive(int $id)
    {
        $dpsType = TipoDespacho::find($id);
        $dpsType->estado = $dpsType->estado === true ? false : true;
        $dpsType->save();
    }


    public function getActivePointSales(): Collection
    {
        return GpPuntoVenta::select([
            'id',
            'PTO_VTA_ID as codigo',
            'PTO_VTA_DESCRIPCION as descripcion'
        ])
            ->where('PTO_VTA_ESTADO', 'ACTIVO')
            ->get();
    }


    public function getDispatchTypesByPaymentResponsible(int $pvId, string $paymentResponsible): Collection
    {
        return TipoDespacho::whereHas('puntosVenta', function ($q) use ($pvId) {
            $q->where('gp_punto_ventas.id', $pvId);
        })
            ->where('responsable_pago', $paymentResponsible)
            ->get(['id', 'descripcion']);
    }
}
