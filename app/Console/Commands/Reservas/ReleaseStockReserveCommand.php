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
            }
        ])->where('tipo_reserva_id', 1)
            ->whereIn('estado', ['aprobado', 'traslado', 'cancelado'])
            ->whereDate('fe_autorizacion_reserva', '<=', $ayer)
            ->whereHas('cotizacion.detallecotizacionstock', function ($q) {
                $q->where('seleccionado', '>', 0);
            });
        $total = 0;
        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $res) {

                try {
                    $released = DB::transaction(function () use ($res) {
                        $quote = $res->cotizacion;
                        if (!$this->releaseStock($quote)) {
                            return false;
                        }
                        $res->update(['estado' => 'cancelado']);
                        return true;
                    });
                    if (!$released) {
                        continue;
                    }
                    $total++;
                    $title = "Reserva liberada";
                    $msg = "Se liberó el stock reservado sin guía pendiente para la cotización {$res->cotizacion->cotizacion}.";
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


    public function releaseStock(GpCotizacion $cot): bool
    {
        $released = false;
        $stocks = GpCotizacionDetalleStock::where('cotizacion_id', $cot->id)
            ->where('seleccionado', '>', 0)
            ->lockForUpdate()->get();

        foreach ($stocks as $stock) {
            $pending = 0.0;
            $transfers = $stock->trasladoreserva()->lockForUpdate()->get();
            $guideTotals = [];
            foreach ($transfers as $transfer) {
                $details = $transfer->detalleguia()
                    ->whereHas('guia', fn ($q) => $q->where('estado', '!=', 'eliminada'))
                    ->lockForUpdate()->get();
                $guideTotals[$transfer->id] = (float) $details->sum('cantidad');
                foreach ($details as $detail) {
                    // Lo recibido ya salió del seleccionado de la bodega origen.
                    $pending += max(0, (float) $detail->cantidad - (float) $detail->recibido);
                }
            }

            $quantity = round(max(0, (float) $stock->seleccionado - $pending), 4);
            if ($quantity <= 0) {
                continue;
            }

            // Conserva lo documentado, incluso recibido, para no reabrir saldo del traslado.
            foreach ($transfers as $transfer) {
                $transfer->update(['cantidad' => min((float) $transfer->cantidad, $guideTotals[$transfer->id])]);
            }

            $this->storeQuotationReservedStockLogs([$stock], $quantity);
            $stock->update([
                'cantidad_cancelada'    => (float) $stock->cantidad_cancelada + $quantity,
                'seleccionado'          => max(0, (float) $stock->seleccionado - $quantity),
                'cantidad_cotizada'     => max(0, (float) $stock->cantidad_cotizada - $quantity),
                'cantidad_reservada'    => max(0, (float) $stock->cantidad_reservada - $quantity),
                'cantidad_traslado'     => max(0, (float) $stock->cantidad_traslado - $quantity),
            ]);
            $released = true;
        }

        return $released;
    }

    public function storeQuotationReservedStockLogs($stocks, ?float $quantity = null)
    {
        $data = [];
        foreach ($stocks as $st) {
            $data[] = [
                'stock_id'   => $st->id,
                'user_id'    => 6,
                'tipo'       => 'liberado_sistema',
                'cantidad'   => $quantity ?? (float) $st->seleccionado,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($data)) {
            DB::table('gp_cotizacion_detalle_stocks_logs')->insert($data);
        }
    }
}
