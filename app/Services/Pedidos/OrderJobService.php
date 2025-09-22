<?php

namespace App\Services\Pedidos;

use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\MswService;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderJobService
{
    use JsonResponseTrait;
    protected $webService;
    protected $invoiceService;
    protected $orderService;
    protected $orderRepository;

    public function __construct(
        MswService $webService,
        InvoiceServiceInterface $invoiceService,
        OrderServiceInterface $orderServiceInterface,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->webService = $webService;
        $this->invoiceService = $invoiceService;
        $this->orderService = $orderServiceInterface;
        $this->orderRepository = $orderRepository;
    }


    /** reemplazo de la función de  facturarsiguientemescron */

    public function sendInvoiceNextMonthCron()
    {
        if ($this->isWeekend()) {
            throw new Exception('No se pueden enviar facturas en fines de semana.');
        }

        if (!$this->isBussinessDay()) {
            throw new Exception('Dia no Habil');
        }

        return $this->invoicePreviousMonthOrders();
    }

    public function isWeekend(): bool
    {
        return Carbon::now()->isWeekend();
    }

    public function isBussinessDay()
    {

        $params = [
            'IDCompania' => env('ID_COMPANIA'),
            'Fecha' => date('Y-m-d'),
        ];

        $diaHabil = $this->webService->cobranzas('ConsultaDiaFeriado', $params);
        Log::channel('cron_process')->warning('Respuesta inesperada del WS ConsultaDiaFeriado', ['response' => $diaHabil]);
        if ($diaHabil['ConsultaDiaFeriadoResult']['Retorno'][0]['Codigo'] == 0) {
            return false;
        }
        return true;
    }



    /** funciones para el facturar siguiente mes */

    public function invoicePreviousMonthOrders()
    {
        [$start, $end, $targetMonthKey] = $this->getPreviousMonthPeriod();
        $m = ['processed' => 0, 'invoiced' => 0, 'skipped' => 0, 'failed' => 0];

        $q = $this->orderRepository->getOrdersToInvoiceNextMonth($start, $end);
        if (!(clone $q)->exists()) {
            Log::channel('cron_process')->info("ℹ️ Sin pedidos para facturar (siguiente mes). target_month={$targetMonthKey}");
            return $m;
        }

        $q->chunkById(200, function ($orders) use (&$m) {
            foreach ($orders as $key => $order) {
                $m['processed']++;

                try {
                    DB::transaction(function () use ($order, &$m) {
                        $fresh = \App\Models\GpPedido::query()
                            ->whereKey($order->id)
                            ->lock('FOR UPDATE') // MySQL 8+: 'FOR UPDATE' o 'FOR UPDATE SKIP LOCKED'
                            ->first();

                        if (!$fresh) {
                            $m['skipped']++;
                            return;
                        }
                        if (in_array($fresh->estado, ['facturado'], true) || !empty($fresh->factura)) {
                            $m['skipped']++;
                            return; // idempotencia
                        }
                        $this->invoiceService->checkSendInvoice($fresh, true, false);
                        $m['invoiced']++;
                        Log::channel('cron_process')->info("✅ Pedido {$fresh->id} facturado (siguiente mes).");
                    });
                } catch (\DomainException $e) {
                    $m['skipped']++;
                    Log::channel('cron_process')->warning("⚠️ Pedido {$order->id} omitido (regla): {$e->getMessage()}");
                } catch (\Throwable $e) {
                    $m['failed']++;
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$order->id}: {$e->getMessage()}", [
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }, 'id');
        Log::channel('cron_process')->info('next-month-invoicing finished', $m + [
            'target_month'   => $targetMonthKey,
            'ended_at'       => now()->toDateTimeString(),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);
        return $m;

        // dd($q->count());
        // DB::beginTransaction();
        // $errores = [];
        // $mes = $this->getPreviousMonth();


        // /** se comento para pruebas  */
        // if ($this->shouldAbortBilling($mes)) {
        //     DB::rollBack();
        //     Log::channel('cron_process')->warning('⚠️ No se ejecuta facturación: Aún no llega el mes :' . $mes);
        //     return;
        // }

        // $pedidos = $this->orderRepository->getOrdersToInvoiceNextMonth($mes);
        // foreach ($pedidos as $pedido) {
        //     try {
        //         $this->invoiceService->checkSendInvoice($pedido, true, false);
        //         Log::channel('cron_process')->info("✅ Pedido {$pedido->id} facturado correctamente.");
        //     } catch (\Throwable $e) {
        //         $errores[] = [
        //             'pedido_id' => $pedido->id,
        //             'error' => $e->getMessage(),
        //         ];

        //         Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->id}: {$e->getMessage()}");
        //     }
        // }
        // if (!empty($errores)) {
        //     DB::rollBack();
        //     Log::channel('cron_process')->error('❌ Proceso de facturación cancelado. Se encontraron errores.', $errores);
        //     return;
        // }
        // DB::commit();
        // Log::channel('cron_process')->info('✅ Todos los pedidos facturados exitosamente.');
    }

    public function getPreviousMonth()
    {
        return date("m", strtotime("-1 month"));
    }

    public function shouldAbortBilling($mes)
    {
        return $mes === date("m");
    }



    public function getPreviousMonthPeriod(?string $runDate = null)
    {

        $ref = $runDate ? Carbon::parse($runDate) : Carbon::now();
        $start = $ref->copy()->startOfMonth()->subMonthNoOverflow();
        $end = $ref->copy()->endOfMonth()->subMonthNoOverflow();
        return [$start, $end, $start->format('Y-m')];
    }


    /** facturacion de pedidos en estado de reserva  */


    public function generateInvoiceForReservedOrders(?string $date = null): array
    {
        $today = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $metrics = ['processed' => 0, 'invoiced' => 0, 'skipped' => 0, 'failed' => 0];
        $orders =  $this->orderRepository->queryInvoiceReservedOrdersForDate($today);
        $orders->orderBy('id')->chunkById(200, function ($orders) use (&$metrics, $today) {
            foreach ($orders as $order) {
                $metrics['processed']++;

                try {
                    DB::transaction(function () use ($order, $today, &$metrics) {
                        // 3) Re-carga con lock para evitar doble facturación concurrente
                        /** @var \App\Models\GpPedido $fresh */
                        $fresh = \App\Models\GpPedido::query()
                            ->whereKey($order->id)
                            ->lock('FOR UPDATE') // MySQL 8+: 'FOR UPDATE' o 'FOR UPDATE SKIP LOCKED'
                            ->first();

                        // Idempotencia: sal si ya está facturado o tiene número de factura, etc.
                        if (in_array($fresh->estado, ['facturado'], true) || !empty($fresh->factura)) {
                            $metrics['skipped']++;
                            Log::channel('cron_process')->info("⏭️ Pedido {$fresh->id} omitido: ya facturado.");
                            return;
                        }

                        // Asegura fecha (si por algún motivo entró fuera de rango)
                        $orderDate = $fresh->fe_facturacion_reserva instanceof Carbon
                            ? $fresh->fe_facturacion_reserva->toDateString()
                            : Carbon::parse($fresh->fe_facturacion_reserva)->toDateString();

                        if ($fresh->origen !== 'reserva' || $orderDate !== $today) {
                            $metrics['skipped']++;
                            Log::channel('cron_process')->info("⏭️ Pedido {$fresh->id} omitido: origen/fecha no coincide.");
                            return;
                        }

                        // 4) Ejecuta la facturación (tu servicio)
                        $this->invoiceService->checkSendInvoice($fresh, true, false);

                        $metrics['invoiced']++;
                        Log::channel('cron_process')->info("✅ Pedido {$fresh->id} facturado correctamente (reserva).");
                    });
                } catch (\DomainException $e) {
                    $metrics['skipped']++;
                    Log::channel('cron_process')->warning("⚠️ Pedido {$order->id} no facturado (regla negocio): {$e->getMessage()}");
                } catch (\Throwable $e) {
                    $metrics['failed']++;
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$order->id}: {$e->getMessage()} - Reserva", [
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        });

        return $metrics;
    }
}
