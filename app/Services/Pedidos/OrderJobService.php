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
        DB::beginTransaction();
        $errores = [];
        $mes = $this->getPreviousMonth();


        /** se comento para pruebas  */
        if ($this->shouldAbortBilling($mes)) {
            DB::rollBack();
            Log::channel('cron_process')->warning('⚠️ No se ejecuta facturación: Aún no llega el mes :' . $mes);
            return;
        }

        $pedidos = $this->orderRepository->getOrdersToInvoiceNextMonth($mes);
        foreach ($pedidos as $pedido) {
            try {
                $this->invoiceService->checkSendInvoice($pedido, true, false);
                Log::channel('cron_process')->info("✅ Pedido {$pedido->id} facturado correctamente.");
            } catch (\Throwable $e) {
                $errores[] = [
                    'pedido_id' => $pedido->id,
                    'error' => $e->getMessage(),
                ];

                Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->id}: {$e->getMessage()}");
            }
        }
        if (!empty($errores)) {
            DB::rollBack();
            Log::channel('cron_process')->error('❌ Proceso de facturación cancelado. Se encontraron errores.', $errores);
            return;
        }
        DB::commit();
        Log::channel('cron_process')->info('✅ Todos los pedidos facturados exitosamente.');
    }

    public function getPreviousMonth()
    {
        return date("m", strtotime("-1 month"));
    }

    public function shouldAbortBilling($mes)
    {
        return $mes === date("m");
    }
}
