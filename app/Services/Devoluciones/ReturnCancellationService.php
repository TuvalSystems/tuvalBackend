<?php

namespace App\Services\Devoluciones;

use App\Enums\devoluciones\DevolutionStatus;
use App\Enums\devoluciones\InvoiceAnulationStatus;
use App\Enums\Ventas\OrderStatus;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use App\Services\Api\ApiSalesService;
use App\Services\Api\ApiSeedBillingService;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;
use App\Services\Devoluciones\Contracts\ReturnCancellationServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnCancellationService implements ReturnCancellationServiceInterface
{
    /**
     *  Servicio de Anulacion de Devoluciones
     */

    public function __construct(
        protected readonly ApiSalesService $apiSales,
        protected readonly ApiSeedBillingService $apiSeedBilling,
        protected readonly DevolucionRepositoryInterface $repository,
        protected readonly DevolucionParamsBuilderInterface $devolucionBuilder
    ) {}


    public function execute(array $order, GpDevoluciones $devolucion)
    {
        Log::channel('devolucion')->info("Procesando anulación Devolución ID: {$devolucion['id']} para Pedido ID: {$order['id']}");

        $newOrder = GpPedido::find($order['id']);
        if (!$newOrder) {
            abort(404, 'Pedido no encontrado');
        }
        if ($this->repository->isInvoiceAlreadyVoided($newOrder, $devolucion)) {
            abort(400, 'La factura ya fue anulada');
        }

        /** obtiene y valida la clave de acceso de la factura */
        $invoice = $this->getOrderAccesKey($newOrder->factura);
        $successInv = data_get($invoice, 'success', false);
        $documentId = data_get($invoice, 'data.id', null);
        $claveAcceso = data_get($invoice, 'data.claveAcceso', null);
        if (!$successInv || !$claveAcceso) {
            $mensajeError = data_get($invoice, 'message', 'Error desconocido al obtener clave');
            $devolucion->update(['estado' => DevolutionStatus::NO_PROCESADO, 'motivo_no_anulada' => $mensajeError]);
            abort(400, "Devolución generada {$devolucion->devolucion} pero no fue anulada por el siguiente motivo : " . $mensajeError);
        }


        /** envia a anular la factura a gp  */
        $diario = $this->sendAnulationTpGpInvoice($newOrder->factura, $newOrder->CLI_RUC, $devolucion->user_id, $devolucion->devolucion);
        if (!data_get($diario, 'success', false)) {
            $mensajeError = data_get($diario, 'message', 'Error desconocido al anular factura en GP');
            $devolucion->update(['estado' => DevolutionStatus::NO_PROCESADO, 'motivo_no_anulada' => $mensajeError]);
            abort(400, "Devolución generada {$devolucion->devolucion} pero no fue anulada por el siguiente motivo : " . $mensajeError);
        }

        $anulation = $this->createRegisterInvoiceAnulation($newOrder, $devolucion, data_get($diario, 'data'), $documentId, $claveAcceso);
        $this->finalizeCancellation($anulation, $newOrder, $devolucion);
        Log::channel('devolucion')->info("Anulación completada Devolución ID: {$devolucion['id']} para Pedido ID: {$order['id']}");
        // return $devolucion->refresh();
    }


    public function getOrderAccesKey(string $invoice)
    {
        return $this->apiSales->retrieveDocumentAccessKey([
            'numdoc' => $invoice
        ]);
    }

    public function sendAnulationTpGpInvoice(string $invoice, string $ruc, int $userId, string $devolucion)
    {
        $params = [
            'numDoc' => $invoice,
            'cliente' => $ruc,
            'userId' => $userId,
            'devolucion' => $devolucion
        ];
        return $this->apiSales->retrieveGpJournalEntry($params);
    }


    protected function createRegisterInvoiceAnulation($order, $devolucion, $diario, $documentId, $claveAcceso): GpFacturaAnulada
    {
        $invoice = $this->devolucionBuilder->formPayloadInvoiceAnulation($order, $devolucion, $diario, $documentId, $claveAcceso);
        $invoice->save();
        return $invoice;
    }


    protected function finalizeCancellation(GpFacturaAnulada $invoice, GpPedido $order, GpDevoluciones $devolucion)
    {
        $resp = $this->apiResponseSeedBelling($invoice->claveacceso);
        $message = data_get($resp, 'respuesta', null);
        DB::transaction(function () use ($invoice, $order, $devolucion, $message) {
            $invoice->update([
                'estado'            => InvoiceAnulationStatus::ENVIADA,
                'resp_seedbilling'  => $message
            ]);

            //1.- sincronizar cantidades (prepara los dato seleccionados para anular)
            $this->syncSelectedQuantities($devolucion);
            //2.- Reversa el strock ahora que las cantidades estan sincronizadas
            $this->processStockReversion($order, $devolucion);
            //3.- actualiza estados finales
            $this->updateStatusDevolutionAndOrder($order, $devolucion);
        });

        $this->apiSales->markGpInvoiceAsVoided(['numdoc' => $order->factura]);
    }


    public function syncSelectedQuantities(GpDevoluciones $devolucion)
    {
        $column = 'seleccionado'; // Por defecto, usamos 'seleccionado'
        $affected = DB::table('gp_devoluciones_detalles')
            ->where('devolucion_id', $devolucion->id)
            // ->where('despachado_parcial', 0)
            ->update([
                'devuelto' => DB::raw("`$column`")
            ]);
        Log::channel('devolucion')->info("Sincronizadas cantidades para Devolución ID {$devolucion->id}: {$affected} registros actualizados.");
        return $affected;
    }

    public function processStockReversion(GpPedido $order, GpDevoluciones $devolucion)
    {

        if ($devolucion->rebajado) {
            return;
        }
        $bodega = $order->BOD_BODEGA;
        $shouldReleaseStock = !$devolucion->despachado;

        $devDetails = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $orderDetails = GpPedidoDetalle::where('pedido_id', $order->id)->get()->keyBy('id');
        foreach ($devDetails as $dvd) {
            $art = $orderDetails->get($dvd->detalle_id);
            if (!$art) {
                Log::channel('devolucion')->warning("No se encontró el detalle de pedido ID {$dvd->detalle_id} para la devolución ID {$devolucion->id}");
                continue;
            }
            $art->increment('devuelto', $dvd->devuelto);
            if ($shouldReleaseStock) {
                $stockRecord = GpPedidoDetalleStock::where('detalle_id', $art->id)
                    ->where('STO_ART_BODEGA', $bodega)
                    ->first();
                if ($stockRecord) {
                    $newReserved = max(0, $stockRecord->reservado - $dvd->devuelto);
                    $stockRecord->update(['reservado' => $newReserved]);
                }
            }
        }
        $devolucion->update(['rebajado' => true]);
    }

    public function updateStatusDevolutionAndOrder(GpPedido $order, GpDevoluciones $devolucion)
    {
        $devolucion->update(['estado' => DevolutionStatus::ANULADA]);
        $order->update([
            'estado'                => OrderStatus::ANULADA,
            'despacho_en_proceso'   => 0,
        ]);

        $this->cancelDispatchOrders($order);
    }
    protected function cancelDispatchOrders(GpPedido $order): void
    {
        GpOrdenDespacho::where('pedido_id', $order->id)
            ->where('estado', '!=', 'cancelada')
            ->update([
                'estado' => 'cancelada',
            ]);
    }


    public function apiResponseSeedBelling(string $accesCode)
    {

        return $this->apiSeedBilling->cancelDocument(array(
            'claveAcceso' => $accesCode,
        ));
    }
}
