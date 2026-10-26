<?php

namespace App\Services\Ventas\Pedidos;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Comercial\CommercialVisibilityService;
use App\Services\Ventas\Pedidos\Contracts\InvoiceHistoryServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceHistoryService implements InvoiceHistoryServiceInterface
{
    public function __construct(
        protected readonly CommercialVisibilityService $commercial
    ) {
        // Inyecta dependencias
    }


    public function fetchInvoiceHistory(GpUsuario $user, int $clientId, int $pvId, string $pvCode, array $filters): LengthAwarePaginator
    {
        $sortField  = data_get($filters, 'sortField', 'fecha_factura');
        $sortOrder  = data_get($filters, 'sortOrder', 'desc');
        $search     = data_get($filters, 'search');
        $perPage    = data_get($filters, 'perpage', 10);
        $sortColumns = [
            'cliente_id'    => 'vhi.cliente_id',
            'agencia'       => 'vhi.agencia',
            'factura'       => 'vhi.factura',
            'codigo'        => 'vhi.codigo',

        ];

        $sortBy = $sortColumns[$sortField] ?? 'vhi.fecha_factura';
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('vw_hist_itemcli as vhi')
            ->select([
                'vhi.cliente_id',
                'vhi.fecha_factura',
                'vhi.agencia',
                'vhi.factura',
                'vhi.codigo',
                'vhi.item3l',
                'vhi.descripcion',
                'vhi.uom',
                'vhi.cantidad',
                'vhi.precio',
                'vhi.descuento',
                'vhi.subtotal',
                'vhi.user_id',
                'vhi.precio',
                'vhi.id_pv as pv_id',
                'vhi.pedido_id'
            ]);
        $query->where('vhi.cliente_id', $clientId);
        $this->commercial->applyLimitsVisibiliteList($query, $user, $pvId, 'pedidos', 'vhi.user_id');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vhi.factura', 'like', '%' . $search . '%')
                    ->orWhere('vhi.codigo', 'like', '%' . $search . '%')
                    ->orWhere('vhi.item3l', 'like', '%' . $search . '%')
                    ->orWhere('vhi.descripcion', 'like', '%' . $search . '%')
                ;
            });
        }

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }
}
