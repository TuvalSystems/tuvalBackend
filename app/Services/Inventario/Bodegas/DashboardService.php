<?php

namespace App\Services\Inventario\Bodegas;

use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodega;
use App\Services\Inventario\Bodegas\Contracts\DashboardServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService implements DashboardServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }



    /**
     * *
     * @param string $ptoVta
     * @return array
     */
    public function listDispatchOrdersByPtoVta(string $ptoVta): array
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');
        $ordenes = $this->getOrdersByPtoVta($bodega);

        $allOrders = $ordenes->map(function ($ord) {
            $ord->peso_total = $ord->pedido->peso_total;
            return $ord;
        });
        $retired = $allOrders->filter(fn($ord) => $ord->pedido->despacho === 'Retiro en bodega');
        $dispatch = $allOrders->filter(fn($ord) => $ord->pedido->despacho === 'Despacho en ruta');
        $ordersNow = $allOrders->filter(fn($ord) => Carbon::parse($ord->fecha_hora_liberacion)->isToday());
        $oldOrders = $allOrders->filter(fn($ord) => !Carbon::parse($ord->fecha_hora_liberacion)->isToday());

        return [
            'allCount'      => $allOrders->count(),
            'maxDays'       => (int) $allOrders->max('dias_transcurridos') ?? 0,
            'totalKg'       => $allOrders->sum('peso_total'),
            'typeRetired'   => $this->formatStats($retired),
            'typeDispatch'  => $this->formatStats($dispatch),
            'ordersNow'     => $this->formatStats($ordersNow),
            'oldOrders'     => $this->formatStats($oldOrders),
        ];
    }

    protected function getOrdersByPtoVta(string $bodega): Collection
    {
        return  GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', 'liberada')
            ->whereHas('pedido', function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado']);
            })
            ->with(['pedido' => function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado'])
                    ->select('id', 'pedido', 'estado', 'despacho')
                    ->withSum('detallepedido as peso_total', 'ART_PESO');
            }])
            ->select('id', 'pedido_id', 'orden', 'estado', 'fecha_hora_liberacion', 'BOD_BODEGA')
            ->selectRaw('DATEDIFF(NOW(), fecha_hora_liberacion) as dias_transcurridos')
            ->orderBy('fecha_hora_liberacion', 'ASC')
            ->get();
    }





    public function countItemServicesDisptach($ptoVta): array
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');
        $orders =  GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', 'liberada')
            ->whereHas('pedido', function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado'])
                    ->whereHas('detallepedido', function ($subQuery) {
                        $subQuery->where('ART_TIPO_COMPRA', 'SERV');
                    });
            })->with(['pedido' => function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado'])
                    ->select('id', 'pedido', 'estado', 'despacho', 'CLI_RAZON_SOCIAL')
                    ->withCount(['detallepedido as totalItemServ' => function ($subQuery) {
                        $subQuery->where('ART_TIPO_COMPRA', 'SERV');
                    }]);
            }])

            ->get();

        $processedOrders = $orders->map(function ($order) {
            $order->totalItemServ = $order->pedido->totalItemServ ?? 0;
            $order->cliente = $order->pedido->CLI_RAZON_SOCIAL ?? 'Sin Nombre';
            return $order;
        });

        $topFiveOrders = $processedOrders->sortByDesc('totalItemServ')
            ->take(5)
            ->values()->map(function ($order) {
                return [
                    'orden' => $order->orden,
                    'cliente' => $order->cliente,
                    'totalItemServ' => $order->totalItemServ,
                ];
            });
        return [
            'totalItemServ' => $processedOrders->sum('totalItemServ'),
            'TopFiveOrderes' => $topFiveOrders
        ];
    }

    protected function formatStats($collection): array
    {
        return [
            'allCount' => $collection->count(),
            'maxDays'  => (int) $collection->max('dias_transcurridos') ?? 0,
            'totalKg'  => $collection->sum('peso_total'),
        ];
    }
}
