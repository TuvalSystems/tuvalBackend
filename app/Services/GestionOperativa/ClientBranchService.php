<?php

namespace App\Services\GestionOperativa;

use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Models\GpUsuario;
use App\Services\GestionOperativa\Contracts\ClientBranchServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Traits\RegisterAudit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientBranchService implements ClientBranchServiceInterface
{
    use RegisterAudit;

    public  function __construct(protected readonly ClientQueryServiceInterface $client) {}


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
        return $query->paginate(
            data_get($filters, 'perpage', 10),
        );
    }


    public function getDetailClientBranches(int $id): array
    {

        $branch = GpClienteSucursal::find($id);
        $cotizaciones = $this->hasBranchInProcessQuotes($branch->id);
        $pedidos =  $this->hasBranchInProcessOrders($branch->id);
        $listQuotes = collect($cotizaciones)->pluck('cotizacion')->unique()->values()->all();
        $lisOrders = collect($pedidos)->pluck('pedido')->unique()->values()->all();
        return [
            'sucursal' => $branch,
            'process_quotes' =>  $cotizaciones->isNotEmpty(),
            'list_quotes' => $listQuotes,
            'process_orders' => $pedidos->isNotEmpty(),
            'list_orders' => $lisOrders
        ];
    }

    protected function hasBranchInProcessQuotes($branchId)
    {
        return GpCotizacion::where(function ($query) use ($branchId) {
            $query->where('sucursal_id', $branchId)
                ->orWhere('sucursal_facturacion_id', $branchId);
        })
            ->whereNotIn('estado', ['procesado', 'rechazado', 'caducado', 'eliminado'])
            ->select(['id', 'cotizacion', 'estado'])->get();
    }

    protected function hasBranchInProcessOrders($branchId)
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
            ->select(['id', 'pedido', 'estado'])
            ->get();
    }





    public function updateStatusClientBranches(int $id, int $estado, GpUsuario $user): GpClienteSucursal
    {
        $branch = GpClienteSucursal::findOrFail($id);
        $oldData = ['estado' => $branch->estado];
        $branch->estado = $estado;
        if ($branch->isDirty()) { // Solo guarda e historializa si realmente hubo un cambio
            $branch->save();

            // 3. Capturamos los nuevos datos y guardamos historial
            $newData = ['estado' => $branch->estado];
            $this->createHistoryRecord($user, $branch, 'UPDATE_STATUS', $oldData, $newData);
        }
        $this->updateStatusBranchInGp($branch, $user);
        return $branch;
    }



    protected function updateStatusBranchInGp(GpClienteSucursal $branch, GpUsuario $userModificador): void
    {
        $fechaModificacion = Carbon::now()->format('Y-m-d H:i:s');
        $this->client->createRegisterBranchClient(
            $branch,
            2,
            null,
            null,
            $userModificador->USERID,
            $fechaModificacion,
            true
        );
    }
}
