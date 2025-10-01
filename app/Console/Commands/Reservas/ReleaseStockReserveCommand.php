<?php

namespace App\Console\Commands\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalleStock;
use App\Notifications\Venta\Reservas\ReservedReleaseNotification;
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
        $reservas = GpCotizacion::query()->with(['usuariosolicitudreserva', 'detallecotizacionstock' => function ($q) {
            $q->where('seleccionado', '>', 0);
        }])->where('st_solResInv', 'aprobado')->where('co_tipSolResInv', 1)
            ->where('fe_autSolResInv', '<=', $hoy);
        $total = 0;
        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $key => $cot) {
                $this->storeQuotationReservedStockLogs($cot->detallecotizacionstock);
                $this->releaseStock($cot);
                $cot->update(['st_solResInv' => 'procesado']);
                $creator = $cot->usuariosolicitudreserva;
                $creator->notify((new ReservedReleaseNotification('Reserva liberada', $cot->cotizacion))->onQueue('notifications'));
                $total++;
                Log::info("reservas:release-stock => $cot->cotizacion");
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
        foreach ($stocks as  $st) {
            DB::table('gp_cotizacion_detalle_stocks_logs')->insert([
                'stock_id' => $st->id,
                'user_id' => 6,
                'tipo' => 'liberar_sistema',
                'cantidad' => (int)$st->seleccionado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
