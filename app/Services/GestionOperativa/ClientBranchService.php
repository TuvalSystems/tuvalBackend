<?php

namespace App\Services\GestionOperativa;


use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Services\GestionOperativa\Contracts\ClientBranchServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientBranchService implements ClientBranchServiceInterface
{



    public function listClientBranches(array $filters): LengthAwarePaginator
    {
        $query = GpClienteSucursal::from('gp_cliente_sucursals as cls')
            ->select([
                'cls.id',
                'cls.cliente_id',
                'cls.CLI_SUC_RUC as ruc',
                'cls.CLI_SUC_DESCRIPCION as razonSocial',
                'cls.CLI_SUC_ID_DIRECCION as idDireccion',
                'cls.CLI_SUC_DIRECCION as direccion',
                'cls.estado',
                'cls.created_at'
            ]);
        if ($search = data_get($filters, 'search')) {
            $query->where(function ($q) use ($search) {
                $q->where('cls.CLI_SUC_RUC', 'like', "%{$search}%")
                    ->orWhere('cls.CLI_SUC_DESCRIPCION', 'like', "%{$search}%")
                    ->orWhere('cls.CLI_SUC_ID_DIRECCION', 'like', "%{$search}%");
            });
        }
        $status = data_get($filters, 'estado');
        if ($status !== null && $status !== '') {
            $query->where('cls.estado', $status);
        }

        $query->orderBy(data_get($filters, 'sortField', 'cls.created_at'), data_get($filters, 'sortOrder', 'desc'));
        return $query->paginate(data_get($filters, 'perpage', 10));
    }


    public function getDetailClientBranches(int $id) : array
    {

        $branch = GpClienteSucursal::find($id);
        return [
            'sucursal' => $branch,
            'process_quotes' => $this->hasBranchInProcessQuotes($branch->id),
            'process_orders' => $this->hasBranchInProcessOrders($branch->id),
        ];
    }

    protected function hasBranchInProcessQuotes($branchId): bool
    {
        return GpCotizacion::where(function ($query) use ($branchId) {
            $query->where('sucursal_id', $branchId)
                ->orWhere('sucursal_facturacion_id', $branchId);
        })
            ->whereNotIn('estado', ['procesado', 'rechazado', 'caducado', 'eliminado'])->exists();
    }

    protected function hasBranchInProcessOrders($branchId): bool
    {
        return GpPedido::where(function ($query) use ($branchId) {
            $query->where('sucursal_id', $branchId)
                ->orWhere('sucursal_facturacion_id', $branchId);
        })
            ->whereNotIn('estado', ['rechazado', 'caducado', 'eliminado'])
            ->where(function ($query) {

                $query->where('estado', 'espera')

                    ->orWhere(function ($q) {
                        $q->where('estado', 'facturado')
                            ->whereHas('ordenDespacho', function ($subQuery) {
                                $subQuery->whereNotIn('estado', ['despachada', 'cancelada']);
                            });
                    });
            })
            ->exists();
    }
}
