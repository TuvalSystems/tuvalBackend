<?php

namespace App\Console\Commands\Reservas;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalleStock;
use App\Models\Ventas\Reservas\GpReserva;
use App\Notifications\User\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseStockReserveCommand extends Command
{
    protected $signature = 'reservas:release-stock';
    protected $description = 'Libera stock de reservas vencidas (fecha_autorizacion <= hoy)';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $tz = config('app.timezone', 'America/Guayaquil');

        $ayer = Carbon::now($tz)->subDay();
        $reservas = GpReserva::with([
            'usuarioingreso',
            'cotizacion',
            'cotizacion.detallecotizacionstock' => function ($q) {
                $q->where('seleccionado', '>', 0);
                $q->whereDoesntHave('trasladoreserva', function ($query) {
                    $query->where('picking', 1);
                });
            }
        ])->where('tipo_reserva_id', 1)
            ->whereIn('estado', ['aprobado', 'traslado', 'cancelado'])
            ->whereDate('fe_autorizacion_reserva', '<=', $ayer)
            ->whereHas('cotizacion.detallecotizacionstock', function ($q) {
                $q->where('seleccionado', '>', 0);
                $q->whereDoesntHave('trasladoreserva', function ($query) {
                    $query->where('picking', 1);
                });
            });
        $total = 0;
        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $res) {

                try {
                    DB::transaction(function () use ($res, &$total) {
                        $quote = $res->cotizacion;
                        $this->storeQuotationReservedStockLogs($quote->detallecotizacionstock);
                        $this->releaseStock($quote);
                        $res->update(['estado' => 'cancelado']);
                        $total++;
                    });
                    $title = "Reserva liberada";
                    $msg = "Se liberó el stock reservado para la cotización {$res->cotizacion->cotizacion}.";
                    $res->usuarioingreso->notify(new UserNotification($title, $msg));
                    Log::channel('reservas_log')->info("reservas:release-stock => {$res->cotizacion->cotizacion} EXITOSO");
                } catch (\Exception $e) {
                    Log::channel('reservas_log')->error("FALLO en reserva ID {$res->id}: " . $e->getMessage());
                    $this->error("Error procesando reserva {$res->id}. Revisar logs.");
                }
            }
        });

        $this->info("Reservas liberadas: {$total}");
        Log::channel('reservas_log')->info("reservas:release-stock => {$total}");
        return self::SUCCESS;
    }


    public function releaseStock(GpCotizacion $cot)
    {
        GpCotizacionDetalleStock::where('cotizacion_id', $cot->id)
            ->whereDoesntHave('trasladoreserva', function ($q) {
                $q->where('picking', 1);
            })
            ->get()
            ->each(function ($stock) {
                $stock->trasladoreserva()
                    ->where('picking', '!=', 1)
                    ->update(['cantidad' => 0]);
                if ($stock->seleccionado > 0) {
                    $stock->update([
                        'cantidad_cancelada'    =>  max($stock->cantidad_autorizada_reserva, $stock->cantidad_cancelada + $stock->seleccionado, 0),
                        'seleccionado'          => 0,
                        'cantidad_cotizada'     => 0,
                        'cantidad_reservada'    => 0,
                        'cantidad_traslado'     => 0,
                    ]);
                }
            });
    }

    public function storeQuotationReservedStockLogs($stocks)
    {
        $data = [];
        foreach ($stocks as $st) {
            $data[] = [
                'stock_id'   => $st->id,
                'user_id'    => 6,
                'tipo'       => 'liberado_sistema',
                'cantidad'   => (float) $st->seleccionado,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($data)) {
            DB::table('gp_cotizacion_detalle_stocks_logs')->insert($data);
        }
    }
}
