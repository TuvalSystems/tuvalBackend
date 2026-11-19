<?php

namespace App\Services\Ventas\Pedidos;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\OrderStatus;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Integraciones\Api\Cobros\ApiBillingService;
use App\Services\Facturacion\Contracts\InvoiceServiceInterface;
use App\Services\Ventas\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Ventas\Compartido\Contracts\OrderAndQuotationServiceInterface;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Ventas\Pedidos\GpPedido;

class OrderJobService
{
    use JsonResponseTrait;


    public function __construct(
        protected readonly ApiBillingService $apiBilling,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly OrderServiceInterface $orderService,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService
    ) {}


    /** reemplazo de la función de  facturarsiguientemescron */

    public function sendInvoiceNextMonthCron()
    {
        $now = now('America/Guayaquil');
        $cacheKey = "ejecutar_facturacion_" . $now->format('m_Y');

        if (Cache::has($cacheKey)) {
            Log::channel('cron_process')->info("⏭️ La facturación de este mes ya fue procesada anteriormente.");
            return ['abortado' => true, 'razon' => 'Ya ejecutado este mes'];
        }

        if ($this->orderAndQuotationService->isWeekend() || !$this->orderAndQuotationService->isBussinessDay()) {
            Log::channel('cron_process')->info("⏳ Día no hábil. Se reintentará mañana automáticamente.");
            return ['abortado' => true, 'razon' => 'Día no habil, proceso se reintentara el día de mañana'];
        }

        $resultados = $this->invoicePreviousMonthOrders();

        if (isset($resultados['abortado']) && $resultados['abortado'] === false && $resultados['fallidos'] === 0) {
            Cache::put($cacheKey, true, now()->addDays(32));
            Log::channel('cron_process')->info("✅ Proceso mensual completado y marcado en caché.");
        }

        return $resultados;
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
                'abortado'  => true,
                'razon'     => "Mes actual no coincide: {$mes}",
                'total'     => 0,
                'exitosos'  => 0,
                'fallidos'  => 0,
                'errores'   => []
            ];
        }

        $resultados = ['abortado' => false, 'mes' => $mes, 'total' => 0, 'exitosos' => 0, 'encolados' => 0, 'fallidos' => 0, 'errores' => []];
        $this->orderRepository->queryOrdersToInvoiceNextMonth($year, $mes)->chunkById(100, function ($pedidos) use (&$resultados) {
            foreach ($pedidos as $pedido) {
                $resultados['total']++;

                // Éxito idempotente si ya está facturado
                if (filled($pedido->factura) && $pedido->estado === OrderStatus::FACTURADO) {
                    $resultados['exitosos']++;
                    continue;
                }

                try {
                    $this->invoiceService->checkSendInvoice($pedido, true, false);
                    $resultados['encolados']++;
                    Log::channel('cron_process')->info("Pedido {$pedido->pedido}: solicitud de facturación registrada.");
                } catch (\Throwable $e) {
                    $resultados['fallidos']++;
                    $resultados['errores'][] = [
                        'pedido_id' => $pedido->id,
                        'pedido'    => $pedido->pedido,
                        'error'     => $e->getMessage(),
                    ];
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->pedido}", [
                        'pedido_id'     => $pedido->id,
                        'error'         => $e->getMessage(),
                        'trace'         => $e->getTraceAsString(),
                    ]);
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
        $metrics = ['processed' => 0, 'invoiced' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        $orders = $this->orderRepository->queryInvoiceReservedOrdersForDate($today);
        $orders->orderBy('id')->chunkById(200, function ($orders) use (&$metrics, $today) {
            foreach ($orders as $order) {
                $metrics['processed']++;

                try {
                    DB::transaction(function () use ($order, $today, &$metrics) {
                        // 3) Re-carga con lock para evitar doble facturación concurrente
                        /** @var \App\Models\Ventas\Pedidos\GpPedido $fresh */
                        $fresh = GpPedido::query()
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

                        if ($fresh->origen !== OrderOrigenEnum::FACTURA_DEMANDA->value || $orderDate !== $today) {
                            $metrics['skipped']++;
                            Log::channel('cron_process')->info("⏭️ Pedido {$fresh->id} omitido: origen/fecha no coincide.");
                            return;
                        }

                        $this->invoiceService->checkSendInvoice($fresh, true, false);
                        $metrics['queued']++;
                        Log::channel('cron_process')->info("Pedido {$fresh->id}: solicitud de facturación registrada.");
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












    /**
     *
     * facturar pedidos en estado enviando
     *
     */

    public function processPendingInvoices()
    {
        $orders = $this->orderRepository->queryOrdersPendingInvoiceProcessing();
        $ordersL = $this->orderRepository->queryPurchaseOrdersPendingInvoiceProcessing();
        if ($orders->isEmpty() && $ordersL->isEmpty()) {
            return;
        }
        Log::channel('cron_process')->info("Pedidos pendientes de facturacion encontrados", [
            'espera_enviando' => $orders->count(),
            'orden_de_compra' => $ordersL->count(),
        ]);
        foreach ($orders as $order) {
            try {
                if($order->factura !== null && $order->factura !== ''){
                    $order->estado = OrderStatus::FACTURADO;
                    $order->save();
                    Log::channel('cron_process')->info("Pedido {$order->pedido} ya tiene factura {$order->factura}, omitiendo.");
                    continue;

                }
                Log::channel('cron_process')->info("Procesando pedido {$order->pedido} en estado '{$order->estado->value}'...");
                $trasladado = $order->estado === OrderStatus::ENVIANDO;
                $this->invoiceService->checkSendInvoice($order, $trasladado);

            } catch (\Throwable $e) {
               Log::channel('cron_process')->error("Error procesando pedido {$order->pedido}: " . $e->getMessage());
               continue;
            }
        }
        foreach ($ordersL as $order) {
            try {
                if ($this->orderService->valdidateOrdersProviders($order)) {
                    $order->estado = OrderStatus::ESPERA;
                    $order->save();
                    $this->invoiceService->checkSendInvoice($order, true);
                }
            } catch (\Throwable $e) {
                Log::channel('cron_process')->error("Error procesando pedido {$order->pedido} en orden_de_compra: " . $e->getMessage());
                continue;
            }
        }
        Log::channel('cron_process')->info("Proceso de facturas pendientes finalizado.");
    }
}
