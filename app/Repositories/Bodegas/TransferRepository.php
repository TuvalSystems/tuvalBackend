<?php

namespace App\Repositories\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Models\traslados\GpTrasladosVentas;
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

    public function getAllSalesTransfersByWarehouse(string $warehouse)
    {
        $filtroPedido = function ($q) {
            $q->whereNotIn('estado', [ // Quité 'gp_pedidos.estado' por 'estado' si tu relación ya apunta al modelo correcto
                OrderStatus::ELIMINADO->value,
                OrderStatus::ANULADA->value,
                OrderStatus::RECHAZADO->value
            ]);
        };
        return GpTrasladosVentas::with([
            'bodDestino:id,BOD_BODEGA,BOD_DESCRIPCION',
            'relacionPuntoVenta:id,PTO_VTA_BOD_BODEGA,PTO_VTA_BOD_PUNTO_VENTA',
            'pedido' => $filtroPedido
        ])
            ->whereHas('pedido', $filtroPedido)
            ->where('bodega_origen', $warehouse)
            ->pendientes()
            ->get();
    }

    public function getAllSalesTransfersByWarehouseOriginAndDestination(string $origin, string $destination, ?string $orderNumber = null)
    {
        $filtroPedido = function ($q) use ($orderNumber) {
            $q->whereNotIn('estado', [
                OrderStatus::ELIMINADO->value,
                OrderStatus::ANULADA->value,
                OrderStatus::RECHAZADO->value
            ])
                ->select('id', 'pedido', 'USERID');
            if ($orderNumber) {
                $q->where('pedido', $orderNumber);
            }
        };

        return GpTrasladosVentas::with([
            'bodDestino:id,BOD_BODEGA,BOD_DESCRIPCION',
            'relacionPuntoVenta:id,PTO_VTA_BOD_BODEGA,PTO_VTA_BOD_PUNTO_VENTA',
            'articulo:id,ART_CODIGO,ART_DECIMAL_CANTIDAD',
            'pedido' => $filtroPedido
        ])
            ->whereHas('pedido', $filtroPedido)
            ->where('bodega_origen', $origin)
            ->where('bodega_destino', $destination)
            ->pendientes()
            ->get();
    }

    public function findCountPendingGuidesByOriginWarehouse(string $bodOrigin)
    {
        return GpGuiaRemision::with([
            // Solo traemos las llaves foráneas necesarias y los campos a mostrar
            'guiaBodegaDestino:id,BOD_BODEGA,BOD_DESCRIPCION',
            'guiaPuntoVentaDestino:id,PTO_VTA_BOD_BODEGA,PTO_VTA_BOD_PUNTO_VENTA'
        ])
            // Seleccionamos la columna pivote (necesaria para el 'with') y calculamos el total
            ->selectRaw('bodega_destino, count(*) as total')
            ->where('bodega_origen', $bodOrigin)
            ->where('estado', 'pendiente')
            ->where('tipo', 'transferencia')
            ->groupBy('bodega_destino')
            ->get()
            ->keyBy('bodega_destino');
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


    public function findGuidesPendingByOriginWarehouse(string $bodOrigin, string $bodDestino)
    {
        return GpGuiaRemision::query()
            ->from('gp_guia_remisions as ggr')
            ->join('gp_bodegas as gpb', 'ggr.bodega_destino', '=', 'gpb.BOD_BODEGA')
            ->where('ggr.bodega_origen', $bodOrigin)
            ->where('ggr.bodega_destino', $bodDestino)
            ->where('ggr.estado', 'pendiente')
            ->where('ggr.tipo', 'transferencia')
            ->select('ggr.id', 'ggr.GUI_REM_GUIA_REMISION','ggr.GUI_REM_BODEGA', 'ggr.GUI_REM_FECHA_INICIO', 'ggr.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',  'ggr.GUI_REM_PLACA')
            ->orderByDesc('ggr.created_at')
            ->get();
    }
}
