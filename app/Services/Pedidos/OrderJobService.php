<?php

namespace App\Services\Pedidos;

use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\MswService;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
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

    /** funcion para enviar las facturas del siguiente mes a gp cada 1 de cad mes
     *  remplazo de sendfacturasgp()
     *
     */

    // public function sendInvoiceNextMonth()
    // {

    //     // Procesar pedidos en estado 'espera'
    //     $this->processPendingOrders();
    //     // Procesar traslados en estado 'orden_de_compra'
    //     $this->processPurchaseOrderTransfers();
    //     Log::channel( 'cron_process' )->info( '✅ [OrderJobService] - Proceso sendInvoiceNextMonth FINALIZADO.' );
    // }

    // public function processPendingOrders()
    // {
    //     // $pedidos = GpPedido::where( 'estado', 'espera' )->get();
    //     $pedidos = GpPedido::where( 'estado', 'espera' )->where( 'id', 290314 )->get();
    //     foreach ( $pedidos as $pedido ) {
    //         try {
    //             $this->invoiceService->checkSendInvoice( $pedido );
    //         } catch ( Exception $e ) {
    //             $this->logOrderError( $pedido, $e, 'enviando factura' );
    //         }
    //     }
    // }

    // public function processPurchaseOrderTransfers()
    // {
    //     $transferOrders = GpPedido::where( 'estado', 'orden_de_compra' )->get();

    //     foreach ( $transferOrders as $order ) {
    //         if ( $this->isOrderReadyForProcessing( $order ) ) {
    //             $this->processReadyOrder( $order );
    //         } else {
    //             $this->logUnprocessedOrder( $order );
    //         }
    //     }
    // }

    // public function isOrderReadyForProcessing( GpPedido $order ): bool
    // {
    //     $supplierDetails = $this->getSupplierDetailsWithPurchaseOrders( $order );
    //     if ( $supplierDetails->isEmpty() ) {
    //         return false;
    //     }

    //     return $this->areAllSupplierDetailsProcessed( $supplierDetails );
    // }

    // public function getSupplierDetailsWithPurchaseOrders( GpPedido $order )
    // {
    //     return GpPedidoDetalleProveedores::join(
    //         'gp_orden_de_compra_generados',
    //         'gp_orden_de_compra_generados.detalle_id',
    //         'gp_pedido_detalle_proveedores.detalle_id'
    // )
    //         ->where( 'pedido_id', $order->id )
    //         ->select( 'gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO' )
    //         ->get();
    // }

    // public function areAllSupplierDetailsProcessed( Collection $supplierDetails )
    // {
    //     foreach ( $supplierDetails as $detail ) {
    //         if ( $detail->recibido === 'no' || !$this->isPurchaseOrderProcessed( $detail ) ) {
    //             return false;
    //         }
    //     }
    //     return true;
    // }

    // public function isPurchaseOrderProcessed( $supplierDetail )
    // {
    //     try {
    //         $parameters = [
    //             'documento_origen' => $supplierDetail->ORDEN_COMPRA_RECIBO,
    //             'tipo_documento' => 1,
    // ];

    //         $result = $this->webService->transaccional( 'MST_Estado_Recibo_Compra', $parameters );
    //         $status = $result[ 'MST_Estado_Recibo_CompraResult' ][ 'CS_ESTADO_RECIBO_COMPRA' ][ 0 ][ 'REC_COM_ESTADO_RECEPCION' ] ?? null;

    //         return $status === 'PROCESADO';
    //     } catch ( Exception $e ) {
    //         $this->logWebServiceError( $supplierDetail, $e );
    //         return false;
    //     }
    // }

    // public function processReadyOrder( GpPedido $order )
    // {
    //     try {
    //         $order->estado = 'espera';
    //         $order->save();
    //         $this->invoiceService->generateDisptachOrder( $order );
    //         $this->invoiceService->sendInvoiceGp( $order );
    //     } catch ( Exception $e ) {
    //         $this->logOrderError( $order, $e, 'procesando despacho/factura' );
    //     }
    // }

    // public function logWebServiceError( $supplierDetail, Exception $e ): void
    // {
    //     Log::channel( 'cron_process' )->error(
    //         "[OrderJobService] - Error en WebService para documento {$supplierDetail->ORDEN_COMPRA_RECIBO}: {$e->getMessage()}"
    // );
    // }

    // public function logOrderError( GpPedido $order, Exception $e, string $action ): void
    // {
    //     Log::channel( 'cron_process' )->error(
    //         "[OrderJobService] - Error {$action} para Pedido ID {$order->id}: {$e->getMessage()}"
    // );
    //     Log::channel( 'cron_process' )->error( $e->getTraceAsString() );
    // }

    // public function logUnprocessedOrder( GpPedido $order ): void
    // {
    //     Log::channel( 'cron_process' )->info(
    //         "❌ [OrderJobService] - Pedido ID {$order->id} NO cumple condiciones para pasar a 'espera'."
    // );
    // }

    /** reemplazo de la función de  facturarsiguientemescron */

    public function sendInvoiceNextMonthCron()
    {
        if ($this->isWeekend()) {
            throw new Exception('No se pueden enviar facturas en fines de semana.');
        }

        if (!$this->isBussinessDay()) {
            throw new Exception('Dia no Habil');
        }

        return $this->generateNextMonthInvoice();
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

    public function generateNextMonthInvoice()
    {
        $now = now('America/Guayaquil');
        $prev = $now->copy()->subMonthNoOverflow();
        $year = (int) $prev->year;
        $mes  = (int) $prev->month;

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
            foreach ($pedidos as  $pedido) {
                $resultados['total']++;

                // Éxito idempotente si ya está facturado
                if (filled($pedido->factura) && $pedido->estado === 'facturado') {
                    $resultados['exitosos']++;
                    continue;
                }

                $lock = Cache::lock("pedido:{$pedido->id}:facturacion", 60);
                if (! $lock->get()) {
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
                    if ($pedido->estado === 'facturado' && filled($pedido->factura)) {
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
                        'pedido'    => $pedido->pedido,
                        'error'     => $e->getMessage(),
                    ];
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->pedido}", [
                        'pedido_id' => $pedido->id,
                        'error'     => $e->getMessage(),
                        'trace'     => $e->getTraceAsString(),
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
}
