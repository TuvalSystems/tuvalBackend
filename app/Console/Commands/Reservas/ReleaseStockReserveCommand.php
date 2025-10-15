<?php

namespace App\Console\Commands\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalleStock;
use App\Models\reservas\GpReserva;
use App\Notifications\Venta\Reservas\UserReservedNotification;
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
        $hoy = Carbon::now($tz)->startOfDay();

        $reservas = GpReserva::with([
            'usuarioingreso',
            'cotizacion',
            'cotizacion.detallecotizacionstock' => function ($q) {
                $q->where('seleccionado', '>', 0);
            }
        ])->where('tipo_reserva_id', 1)->where('estado', 'aprobado')->where('fe_autorizacion_reserva', '<=', $hoy);

        $total = 0;
        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $key => $res) {
                $quote = $res->cotizacion;
                $userSolicita = $res->usuarioingreso;
                $this->storeQuotationReservedStockLogs($quote->detallecotizacionstock);
                $this->releaseStock($quote);
                $res->update(['estado' => 'procesado']);
                $title = "Reserva liberada";
                $msg = "Se liberó el stock reservado para la cotización {$quote->cotizacion}.";
                $userSolicita->notify((new UserReservedNotification($title, $msg))->onQueue('notifications'));
                $total++;
                Log::info("reservas:release-stock => $quote->cotizacion");
            }
        });

        $this->info("Reservas liberadas: {$total}");
        Log::info("reservas:release-stock => {$total}");
        return self::SUCCESS;
    }


    public function releaseStock(GpCotizacion $cot)
    {
        GpCotizacionDetalleStock::where('cotizacion_id', $cot->id)
            ->lockForUpdate()
            ->get()
            ->each(function ($stock) {
                $stock->update([
                    'seleccionado' => 0
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
