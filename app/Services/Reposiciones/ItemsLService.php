<?php

namespace App\Services\Reposiciones;

use App\Models\GpArticulo;
use App\Models\GpDatosItemsL;
use App\Models\GpTempCart;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ItemsLService
{


    public function getListArticleL()
    {
        return GpArticulo::where('ART_TIPO_COMPRA', 'L')
            ->where('ART_ESTADO', 'ACTIVO')
             ->where('ART_CODIGO', '=', '000-001696-L')
            ->get();
    }


    public function getDataItemLMaxDay()
    {
        return  GpDatosItemsL::get()->keyBy('DATOS_ITEMS_L_CLASE');
    }

    public function isTemporalCart($articulo)
    {
        return GpTempCart::where('ART_CODIGO', $articulo)->exists();
    }



    public function lastActivityForItem(string $articulo)
    {
        $active = DB::table('gp_pedido_detalles as d')
            ->join('gp_pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->where('d.ART_CODIGO', $articulo)
            ->whereIn('p.estado', ['espera', 'traslado', 'orden_de_compra'])
            ->max('p.created_at');

        $invoicedPendingDispatch = DB::table('gp_pedido_detalles as d')
            ->join('gp_pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->where('d.ART_CODIGO', $articulo)
            ->where('p.estado', 'facturado')
            ->where(function ($q) {
                $q->whereNull('p.despachado')->orWhere('p.despachado', 0);
            })
            ->max('p.created_at');

        $invoicedDispatched = DB::table('gp_pedido_detalles as d')
            ->join('gp_pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->where('d.ART_CODIGO', $articulo)
            ->where('p.estado', 'facturado')
            ->where('p.despachado', 1)
            ->max('p.created_at');
        $candidatas = collect([$active, $invoicedPendingDispatch, $invoicedDispatched])->filter();
        if ($candidatas->isEmpty()) {
            return null;
        }

        return Carbon::parse($candidatas->max());
    }

    public function availabilityForItem(string $articulo, $cooldownDays): array
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $last = $this->lastActivityForItem($articulo);

        if (!$last) {
            return [
                'disponible' => true,
                'fecha_ultima_actividad' => null,
                'dias_transcurridos' => null,
                'dias_restantes' => null,
                'dias_enfriamiento' => $cooldownDays,
            ];
        }

        // Asegura TZ y conserva la hora original para almacenar
        $lastTz = $last instanceof Carbon
            ? $last->copy()->tz($tz)
            : Carbon::parse($last, $tz);

        // Días "de calendario": 10 vs 11 => 1
        $lastDay = $lastTz->copy()->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        $elapsed = $lastDay->diffInDays($today);
        $remaining = max(0, $cooldownDays - $elapsed);
        return [
            'disponible' => $elapsed >= $cooldownDays,
            'fecha_ultima_actividad' =>  $lastTz->toDateTimeString(),
            'dias_transcurridos' => $elapsed,
            'dias_restantes' => $remaining,
            'dias_enfriamiento' => $cooldownDays,
        ];
    }
}
