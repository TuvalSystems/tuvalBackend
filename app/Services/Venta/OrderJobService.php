<?php

namespace App\Services\Venta;

use App\Enums\Ventas\OrderStatus;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderJobService
{
    use JsonResponseTrait;


    public function __construct(
        protected readonly ApiBillingService $apiBilling,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly OrderServiceInterface $orderService,
        protected readonly OrderRepositoryInterface $orderRepository
    ) {

    }


    /** reemplazo de la función de  facturarsiguientemescron */

    public function sendInvoiceNextMonthCron()
    {
        if ($this->isWeekend()) {
            throw new Exception('No se pueden enviar facturas en fines de semana.');
        }

        if (!$this->isBussinessDay()) {
           return false;
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
            'idCompany' => env('ID_COMPANIA'),
            'fecha' => Carbon::now()->format('Y-m-d'),
        ];

        $resp = $this->apiBilling->validateBusinessDate($params);
        log::channel('cron_process')->warning('Respuesta del WS ConsultaDiaFeriado', ['response' => $resp]);
        $data = data_get($resp, 'data', []);
        if (data_get($data, 'codigo') == 0) {
            return false;
        }
        return true;
    }



    /** funciones para el facturar siguiente mes */

    public function invoicePreviousMonthOrders()
    {
        $now = now('America/Guayaquil');
        $prev = $now->copy()->subMonthNoOverflow();
        $year = (int) $prev->year;
        $mes = (int) $prev->month;

        if ($this->shouldAbortBilling($mes)) {
            Log::channel('cron_process')->warning("⚠️ No se ejecuta facturación: Aún no llega el mes: {$mes}");
            return [
                'abortado' => true,
                'razon' => "Mes actual no coincide: {$mes}",
                'total' => 0,
                'exitosos' => 0,
                'fallidos' => 0,
                'errores' => []
            ];
        }
        $resultados = ['abortado' => false, 'mes' => $mes, 'total' => 0, 'exitosos' => 0, 'fallidos' => 0, 'errores' => []];
        $this->orderRepository->queryOrdersToInvoiceNextMonth($year, $mes)->chunkById(100, function ($pedidos) use (&$resultados) {
            foreach ($pedidos as $pedido) {
                $resultados['total']++;

                // Éxito idempotente si ya está facturado
                if (filled($pedido->factura) && $pedido->estado === OrderStatus::FACTURADO) {
                    $resultados['exitosos']++;
                    continue;
                }

                $lock = Cache::lock("pedido:{$pedido->id}:facturacion", 60);
                if (!$lock->get()) {
                    // Otro proceso ya está facturando → no lo cuentes como error
                    Log::channel('cron_process')->info("🔒 Lock activo, omito pedido {$pedido->pedido}");
                    continue;
                }
                try {
                    try {

                        $this->invoiceService->checkSendInvoice($pedido, true, false);
                    } catch (\Throwable $exGp) {
                        // Clasifica “ya facturado” como éxito idempotente
                        $msg = mb_strtolower($exGp->getMessage());
                        if (str_contains($msg, 'ya se encuentra facturado') || str_contains($msg, 'ya existe')) {
                            $resultados['exitosos']++;
                            Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} ya estaba facturado (idempotente)");
                        } else {
                            throw $exGp;
                        }
                    }

                    $pedido->refresh();
                    if ($pedido->estado === OrderStatus::FACTURADO && filled($pedido->factura)) {
                        $resultados['exitosos']++;
                        Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} facturado: {$pedido->factura}");
                    } else {
                        Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} no cambió a 'facturado'");
                        //  throw new Exception("El pedido no cambió a 'facturado'");
                    }
                } catch (\Throwable $e) {
                    $resultados['fallidos']++;
                    $resultados['errores'][] = [
                        'pedido_id' => $pedido->id,
                        'pedido' => $pedido->pedido,
                        'error' => $e->getMessage(),
                    ];
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->pedido}", [
                        'pedido_id' => $pedido->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                } finally {
                    optional($lock)->release();
                }
            }
        });

        Log::channel('cron_process')->info("📊 Resumen facturación masiva", $resultados);
        return $resultados;
    }



    public function shouldAbortBilling($mes)
    {
        return $mes === date("m");
    }


    /** facturacion de pedidos en estado de reserva  */


    public function generateInvoiceForReservedOrders(?string $date = null): array
    {
        $today = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $metrics = ['processed' => 0, 'invoiced' => 0, 'skipped' => 0, 'failed' => 0];
        $orders = $this->orderRepository->queryInvoiceReservedOrdersForDate($today);
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
                        if (in_array($fresh->estado, [OrderStatus::FACTURADO], true) || !empty($fresh->factura)) {
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
