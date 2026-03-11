<?php

namespace App\Repositories\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Repositories\Bodegas\Contracts\TransferRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TransferRepository implements TransferRepositoryInterface
{

    public function findPendingSalesTransfersByOriginWarehouse(string $bodega, ?string $orderNumber = null)
    {
        $q = DB::table('gp_traslados_ventas')
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_traslados_ventas.pedido_id')
            ->join('gp_bodegas', 'gp_traslados_ventas.bodega_destino', '=', 'gp_bodegas.BOD_BODEGA')
            ->join('gp_articulos', 'gp_articulos.ART_CODIGO', '=', 'gp_traslados_ventas.ART_CODIGO')
            ->where('gp_traslados_ventas.bodega_origen', $bodega)
            ->where(function ($q) {
                $q->where('gp_traslados_ventas.completado', 0)->orWhereNull('gp_traslados_ventas.completado');
            })
            ->whereNotIn('gp_pedidos.estado', [
                OrderStatus::ELIMINADO->value,
                OrderStatus::ANULADA->value
            ])
            ->when($orderNumber, function ($q) use ($orderNumber) {
                $q->where('gp_pedidos.pedido', $orderNumber);
            })->select([
                'gp_traslados_ventas.*',
                'gp_pedidos.pedido',
                'gp_bodegas.BOD_DESCRIPCION',
                'gp_bodegas.BOD_BODEGA',
                DB::raw('gp_pedidos.created_at AS fecha'),
                DB::raw('gp_pedidos.USERID AS usuario'),
                'gp_articulos.ART_DECIMAL_CANTIDAD'
            ]);
        return $q->get();
    }


    public function findGuidesToWarehouseDestinoAndOrigin($bodOrigin, $destId)
    {
        return GpGuiaRemision::query()
            ->join('gp_bodegas', 'gp_guia_remisions.bodega_destino', '=', 'gp_bodegas.BOD_BODEGA')
            ->where('gp_guia_remisions.bodega_origen', $bodOrigin)
            ->where('gp_guia_remisions.bodega_destino', $destId)
            ->where('gp_guia_remisions.estado', 'pendiente')
            ->where('gp_guia_remisions.tipo', 'transferencia')
            ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
            ->orderByDesc('gp_guia_remisions.created_at')
            ->get();
    }
}
