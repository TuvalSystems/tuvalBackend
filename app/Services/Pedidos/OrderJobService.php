<?php

namespace App\Services\Pedidos;

use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\WebserviceController;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleProveedores;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class OrderJobService
{

    protected $webService;
    protected $pedidoController;
    protected $despachoController;
    protected $invoiceService;
    public function __construct(WebserviceController $webService, PedidosController $pedidoController, DespachosController $despachoController, InvoiceServiceInterface $invoiceService)
    {
        $this->webService = $webService;
        $this->pedidoController = $pedidoController;
        $this->despachoController = $despachoController;
        $this->invoiceService = $invoiceService;

    }



    /** funcion para enviar las facturas del siguiente mes a gp cada 1 de cad mes
     *  remplazo de sendfacturasgp()
     *
     */

    public function sendInvoiceNextMonth()
    {

        // Procesar pedidos en estado 'espera'
        $this->processPendingOrders();
        // Procesar traslados en estado 'orden_de_compra'
        $this->processPurchaseOrderTransfers();
        Log::channel('cron_process')->info('✅ [OrderJobService] - Proceso sendInvoiceNextMonth FINALIZADO.');
    }


    public function processPendingOrders()
    {
        $pedidos = GpPedido::where('estado', 'espera')->get();
        foreach ($pedidos as $pedido) {
            try {
                $this->invoiceService->checkSendInvoice($pedido);
            } catch (Exception $e) {
                $this->logOrderError($pedido, $e, 'enviando factura');
            }
        }
    }



    public function processPurchaseOrderTransfers()
    {
        $transferOrders = GpPedido::where('estado', 'orden_de_compra')->get();

        foreach ($transferOrders as $order) {
            if ($this->isOrderReadyForProcessing($order)) {
                $this->processReadyOrder($order);
            } else {
                $this->logUnprocessedOrder($order);
            }
        }
    }


    public function isOrderReadyForProcessing(GpPedido $order): bool
    {
        $supplierDetails = $this->getSupplierDetailsWithPurchaseOrders($order);
        if ($supplierDetails->isEmpty()) {
            return false;
        }

        return $this->areAllSupplierDetailsProcessed($supplierDetails);
    }

    public function getSupplierDetailsWithPurchaseOrders(GpPedido $order)
    {
        return GpPedidoDetalleProveedores::join(
            'gp_orden_de_compra_generados',
            'gp_orden_de_compra_generados.detalle_id',
            'gp_pedido_detalle_proveedores.detalle_id'
        )
            ->where('pedido_id', $order->id)
            ->select('gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO')
            ->get();
    }

    public function areAllSupplierDetailsProcessed(Collection $supplierDetails)
    {
        foreach ($supplierDetails as $detail) {
            if ($detail->recibido === 'no' || !$this->isPurchaseOrderProcessed($detail)) {
                return false;
            }
        }
        return true;
    }

    public function isPurchaseOrderProcessed($supplierDetail)
    {
        try {
            $parameters = [
                'documento_origen' => $supplierDetail->ORDEN_COMPRA_RECIBO,
                'tipo_documento' => 1,
            ];

            $result = $this->webService->transaccional('MST_Estado_Recibo_Compra', $parameters);
            $status = $result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION'] ?? null;

            return $status === 'PROCESADO';
        } catch (Exception $e) {
            $this->logWebServiceError($supplierDetail, $e);
            return false;
        }
    }


    public function processReadyOrder(GpPedido $order)
    {
        try {
            $order->estado = 'espera';
            $order->save();
            $this->invoiceService->generateDisptachOrder($order);
            $this->invoiceService->sendInvoiceGp($order);
        } catch (Exception $e) {
            $this->logOrderError($order, $e, 'procesando despacho/factura');
        }
    }




    public function logWebServiceError($supplierDetail, Exception $e): void
    {
        Log::channel('cron_process')->error(
            "[OrderJobService] - Error en WebService para documento {$supplierDetail->ORDEN_COMPRA_RECIBO}: {$e->getMessage()}"
        );
    }


    public function logOrderError(GpPedido $order, Exception $e, string $action): void
    {
        Log::channel('cron_process')->error(
            "[OrderJobService] - Error {$action} para Pedido ID {$order->id}: {$e->getMessage()}"
        );
        Log::channel('cron_process')->error($e->getTraceAsString());
    }

    public function logUnprocessedOrder(GpPedido $order): void
    {
        Log::channel('cron_process')->info(
            "❌ [OrderJobService] - Pedido ID {$order->id} NO cumple condiciones para pasar a 'espera'."
        );
    }

}
