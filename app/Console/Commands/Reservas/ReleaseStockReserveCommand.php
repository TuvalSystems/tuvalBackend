<?php

namespace App\Console\Commands\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalleStock;
use App\Models\Reservas\GpReserva;
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
        ])->where('tipo_reserva_id', 1)->whereIn('estado', ['aprobado', 'traslado'])->whereDate('fe_autorizacion_reserva', '<=', $ayer);
        $total = 0;
        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as  $res) {

                $quote = $res->cotizacion;
                $userSolicita = $res->usuarioingreso;

                $this->storeQuotationReservedStockLogs($quote->detallecotizacionstock);
                $this->releaseStock($quote);
                $res->update(['estado' => 'cancelado']);
                $title      = "Reserva liberada";
                $msg        = "Se liberó el stock reservado para la cotización {$quote->cotizacion}.";
                $userSolicita->notify((new UserNotification($title, $msg)));
                $total++;
                Log::channel('reservas_log')->info("reservas:release-stock => $quote->cotizacion");
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

                $stock->update([
                    'cantidad_cancelada'    => $stock->cantidad_cancelada + $stock->seleccionado,
                    'seleccionado'          => 0,
                    'cantidad_cotizada'     => 0,
                    'cantidad_reservada'    => 0,
                    'cantidad_traslado'     => 0,
                ]);
            });
    }

    public function storeQuotationReservedStockLogs($stocks)
    {
        foreach ($stocks as $st) {
            DB::table('gp_cotizacion_detalle_stocks_logs')->insert([
                'stock_id' => $st->id,
                'user_id' => 6,
                'tipo' => 'liberado_sistema',
                'cantidad' => (int) $st->seleccionado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
