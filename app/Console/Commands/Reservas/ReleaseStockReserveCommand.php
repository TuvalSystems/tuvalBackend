<?php

namespace App\Console\Commands\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalleStock;
use App\Notifications\Venta\Reservas\ReservasLiberadasNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
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
        $reservas = GpCotizacion::query()->with('usuariosolicitudreserva')->where('st_solResInv', 'aprobado')->where('co_tipSolResInv', 1)
            ->where('fe_autSolResInv', '<=', $hoy);
        $total = 0;

        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $key => $cot) {
                $this->releaseStock($cot);
                $creator = $cot->usuariosolicitudreserva;
                $total++;
                // $creator->notify((new ReservasLiberadasNotification('Reserva liberada', $cot->cotizacion))->onQueue('notifications'));
            }
        });

        $this->info("Reservas liberadas: {$total}");
        Log::info("reservas:release-stock => {$total}");
        return self::SUCCESS;
    }


    public function releaseStock(GpCotizacion $cot)
    {
       $stocks = GpCotizacionDetalleStock::where('cotizacion_id', $cot->id)
            ->lockForUpdate()
            ->get()
            ->each(function ($stock) {
                $stock->update([
                    'seleccionado' => 0
                ]);
            });
    }
}
