<?php

namespace App\Services\Venta\Cotizaciones;

use App\Models\GpUsuario;
use App\Models\Ventas\Cotizaciones\TipoDespacho;
use App\Services\Venta\Cotizaciones\Contracts\DispatchTypeServiceInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

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
        $now =  Carbon::now();
        $dpsType                    = new TipoDespacho();
        $dpsType->descripcion       = data_get($data, 'descripcion');
        $dpsType->observacion       = data_get($data, 'observacion');
        $dpsType->responsable_pago   = data_get($data, 'responsable_pago');
        $dpsType->usuario_ingreso_id = $user->id;
        $dpsType->fecha_ingreso = $now;
        $dpsType->save();
    }


    public function update(int $id, array $data, GpUsuario $user)
    {
        $now =  Carbon::now();
        $dpsType                        = TipoDespacho::find($id);
        $dpsType->descripcion           = data_get($data, 'descripcion');
        $dpsType->observacion           = data_get($data, 'observacion');
        $dpsType->responsable_pago       = data_get($data, 'responsable_pago');
        $dpsType->usuario_modifica_id   = $user->id;
        $dpsType->fecha_modificacion    = $now;
        $dpsType->save();
    }


    public function activeOrInactive(int $id)
    {
        $dpsType = TipoDespacho::find($id);
        $dpsType->estado = $dpsType->estado === true ? false : true;
        $dpsType->save();
    }
}
