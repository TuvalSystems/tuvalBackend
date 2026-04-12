<?php

namespace App\Console\Commands\Articulos;

use App\Enums\Ventas\OrderStatus;
use App\Models\GpArticulo;
use App\Services\Reposiciones\ItemsLService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActualizacionUsoItemLCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articulol:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para actualizar la disponibilidad de los articulos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ItemsLService $itemsLService)
    {
        $diasMax = $itemsLService->getDataItemLMaxDay(); // keyBy('DATOS_ITEMS_L_CLASE')

        GpArticulo::where('ART_TIPO_COMPRA', 'L')
            ->where('ART_ESTADO', 'ACTIVO')
            ->select('id', 'ART_CODIGO', 'ART_CLASE_ARTICULO')
            ->chunkById(1000, function ($chunk) use ($diasMax) {

                $codes = $chunk->pluck('ART_CODIGO')->all();

                // 1) última actividad por código (1 query)
                $lastMap = DB::table('gp_pedido_detalles as d')
                    ->join('gp_pedidos as p', 'p.id', '=', 'd.pedido_id')
                    ->whereIn('d.ART_CODIGO', $codes)
                    ->whereIn('p.estado', [
                        OrderStatus::ESPERA->value,
                        OrderStatus::TRASLADO->value,
                        OrderStatus::ORDEN_DE_COMPRA->value,
                        OrderStatus::FACTURADO->value,
                        OrderStatus::ENVIANDO->value,
                        OrderStatus::ELIMINADO->value,
                        OrderStatus::RECHAZADO->value,
                        OrderStatus::ANULADA->value,
                        OrderStatus::PROCESADO->value
                    ])
                    ->selectRaw('d.ART_CODIGO, MAX(p.created_at) as last')
                    ->groupBy('d.ART_CODIGO')
                    ->pluck('last', 'd.ART_CODIGO')
                    ->all();


                // 2) en carrito temporal (1 query)
                $inCart = DB::table('gp_temp_carts')
                    ->whereIn('ART_CODIGO', $codes)
                    ->distinct()
                    ->pluck('ART_CODIGO')
                    ->all();

                $inCartSet = array_flip($inCart);

                $tz = config('app.timezone', 'America/Guayaquil');
                $today = Carbon::now($tz)->startOfDay();

                $rows = [];
                foreach ($chunk as $art) {
                    $cooldown = (int) (optional($diasMax->get($art->ART_CLASE_ARTICULO))->DATOS_ITEMS_L_DIA_MAXIMO ?? 60);
                    $last = isset($lastMap[$art->ART_CODIGO]) ? Carbon::parse($lastMap[$art->ART_CODIGO], $tz) : null;

                    if ($last) {
                        $elapsed = $last->copy()->startOfDay()->diffInDays($today);
                        $disp = $elapsed >= $cooldown;
                        if (isset($inCartSet[$art->ART_CODIGO])) $disp = false;
                        $rows[] = [
                            'id'                      => $art->id,
                            'disponible'              => $disp ? 1 : 0,
                            'fecha_ultima_actividad'  => $last->toDateTimeString(),
                            'dias_transcurridos'      => $elapsed,
                            'dias_restantes'          => max(0, $cooldown - $elapsed),
                            'dias_enfriamiento'       => $cooldown,
                        ];
                    } else {
                        $disp = !isset($inCartSet[$art->ART_CODIGO]);
                        $rows[] = [
                            'id'                      => $art->id,
                            'disponible'              => $disp ? 1 : 0,
                            'fecha_ultima_actividad'  => null,
                            'dias_transcurridos'      => null,
                            'dias_restantes'          => $cooldown,
                            'dias_enfriamiento'       => $cooldown,
                        ];
                    }
                }

                // 3) bulk upsert por id (1 query)
                foreach ($rows as $r) {
                    DB::table('gp_articulos')
                        ->where('id', $r['id'])
                        ->update([
                            'disponible'             => $r['disponible'],
                            'fecha_ultima_actividad' => $r['fecha_ultima_actividad'], // puede ser null
                            'dias_transcurridos'     => $r['dias_transcurridos'],      // puede ser null
                            'dias_restantes'         => $r['dias_restantes'],
                            'dias_enfriamiento'      => $r['dias_enfriamiento'],
                            // opcional: si quieres tocar updated_at manualmente
                            'updated_at'          => now(),
                        ]);
                }
            });

        return 0;
    }
}
