<?php

namespace App\Console\Commands\Pedidos;

use App\Jobs\Venta\Pedidos\SyncGpOrdersJob;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportDataToGp extends Command
{
    protected $signature = 'import:orders-mysql-history {start_date} {end_date}';
    protected $description = 'Migra datos históricos desde MySQL hacia SQL Server (GP) para evitar timeouts en la API';


    public function handle(OrdersImportInterface $import)
    {
        $start  = Carbon::parse($this->argument('start_date'))->startOfDay();
        $end    = Carbon::parse($this->argument('end_date'))->startOfDay();

        if ($start->gt($end)) {
            $this->error('la fecha inicial no puede ser mayor que la fecha final.');
            return self::FAILURE;
        }

        while ($start->lte($end)) {
            $periodStart    = $start->copy();
            $periodEnd      = $start->copy()->endOfMonth();

            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }
            $fechaIni = $periodStart->copy()->startOfDay()->format('Y-m-d H:i:s');
            $fechaFin = $periodEnd->copy()->endOfDay()->format('Y-m-d H:i:s');
            $this->info("Encolando periodo: $fechaIni al $fechaFin");
            $import->getOrdersByUpdate($fechaIni, $fechaFin)
                ->chunkById(500, function ($orders) use ($import) {
                    $ids = $orders->pluck('id')->values()->toArray();

                    if (empty($ids)) {
                        return;
                    }

                    // Marcar como "en proceso" antes de encolar
                    $import->markOrdersAsInProcess($ids);


                    $payload = $orders->map(function ($order) {
                        return [
                            'id'            => $order->id,
                            'pedido'        => $order->pedido,
                            'userApproval'  => $order->usuarioComercial?->USERID,
                        ];
                    })->values()->toArray();

                    SyncGpOrdersJob::dispatch('orders', $payload)->onQueue('sync-low');
                    // SyncGpOrdersJob::dispatchSync('orders', $payload);
                    $this->info('Encolado chunk de pedidos: ' . count($payload));
                });
            $import->getOrdersDetailByUpdate($fechaIni, $fechaFin)
                ->chunkById(500, function ($details) use ($import) {
                    $ids = $details->pluck('id')->values()->toArray();
                    if (empty($ids)) {
                        return;
                    }
                    $import->markOrderDetailsAsInProcess($ids);
                    $payload = $details->map(function ($detail) {
                        return [
                            'id'            => $detail->id,
                            'pedido_id'     => $detail->pedido_id,
                            'pedido'        => $detail->pedido?->pedido,
                            'orden'         => $detail->orden,
                            'artCodigo'     => $detail->ART_CODIGO,
                            'descuento'     => $detail->descuento,
                        ];
                    })->values()->toArray();
                    SyncGpOrdersJob::dispatch('details', $payload)->onQueue('sync-low');
                    // SyncGpOrdersJob::dispatchSync('details', $payload);
                    $this->info('Encolado chunk de detalles: ' . count($payload));
                });

            $start = $periodEnd->copy()->addDay()->startOfDay();
        }
        $this->info("Todos los periodos han sido encolados.");
        return self::SUCCESS;
    }
}
