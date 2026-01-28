<?php

namespace App\Services\Bodegas;

use App\Models\GpOrdenDespacho;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Bodegas\Contracts\DashboardServiceInterface;
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
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function listDispatchOrdersByPtoVta(string $ptoVta): Collection
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');
        return GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', 'liberada')
            ->whereHas('pedido', function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado']);
            })
            ->with(['pedido' => function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado'])
                    ->select('id', 'pedido', 'estado')
                    ->withSum('detallepedido as peso_total', 'ART_PESO');
            }])
            ->select('id', 'pedido_id', 'orden', 'estado', 'fecha_hora_liberacion', 'BOD_BODEGA')
            ->orderBy('fecha_hora_liberacion', 'ASC')->get()
            ->map(function ($ord) {
                if ($ord->fecha_hora_liberacion) {
                    $ord->dias_transcurridos = Carbon::parse($ord->fecha_hora_liberacion)->diffInDays(now());
                } else {
                    $ord->dias_transcurridos = 0;
                }
                return $ord;
            });
    }


    public function countItemServicesDisptach($ptoVta): int{
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');
        return GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', 'liberada')
            ->whereHas('pedido', function ($query) {
                $query->whereNotIn('estado', ['eliminado', 'anulada', 'rechazado'])
                ->whereHas('detallepedido', function ($subQuery) {
                      $subQuery->where('ART_TIPO_COMPRA', 'SERV');
                  });
            })
            ->count();
    }
}
