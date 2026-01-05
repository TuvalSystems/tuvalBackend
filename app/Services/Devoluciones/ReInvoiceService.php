<?php

namespace App\Services\Devoluciones;

use App\Enums\devoluciones\DevolutionStatus;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Repositories\Devoluciones\DevolucionRepository;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\ApiGpService;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ReInvoiceService implements ReInvoiceServiceInterface
{
    /**
     *  servicio dedicado al proceso de volver a facturar
     */
    public function __construct(
        protected readonly DevolucionRepository $devRepo,
        protected readonly OrderRepositoryInterface $orderRepo,
        protected readonly FunctionsServiceInterface $functions,
        protected readonly InvoiceServiceInterface $invoice,
        protected readonly ApiGpService $api
    ) {
    }



    public function execute(GpDevoluciones $devolucion)
    {
        $orderOrigin    = GpPedido::where('id', $devolucion->pedido_id)->first();
        $isDispatch     = $this->devRepo->shouldMarkAsDispatched($devolucion->id);
        $cliente        = GpCliente::findOrFail($devolucion->cliente_volver_a_facturar_id);
        $newOrderHeader = $this->createOrderHeader($devolucion, $orderOrigin, $isDispatch, $cliente);

        $detailOrigin   = GpPedidoDetalle::where('pedido_id', $orderOrigin->id)->get();
        $this->processDetailOrder($detailOrigin, $newOrderHeader, $orderOrigin, $devolucion, $isDispatch);
        $this->functions->recalculateOrder($newOrderHeader->id);
        $this->invoice->checkSendInvoice($newOrderHeader);
        return $newOrderHeader;
    }



    public function createOrderHeader(GpDevoluciones $devolucion, GpPedido $orderOrigin, bool $isDispatch, GpCliente $cliente)
    {
        $newOrd                             = $orderOrigin->replicate();
        $newOrd->pedido                     = null; //esto lo crea el observer que generamos (se crea automaticamente)
        $newOrd->factura                    = null;
        $newOrd->factura_origen             = $orderOrigin->factura;
        $newOrd->fecha_factura              = null;
        $newOrd->estado                     = DevolutionStatus::ESPERA;
        $newOrd->despachado                 = $isDispatch;
        $newOrd->sucursal_id                = $orderOrigin->sucursal_id;
        $newOrd->sucursal_facturacion_id    = $orderOrigin->sucursal_facturacion_id;
        $newOrd->cliente_id                 = $devolucion->cliente_volver_a_facturar_id;
        $newOrd->orden_de_compra            = $orderOrigin->orden_de_compra;
        $newOrd->nota_cliente               = $orderOrigin->nota_cliente;
        $newOrd->CLI_RUC                    = $cliente->CLI_RUC;
        $newOrd->CLI_RAZON_SOCIAL           = $cliente->CLI_RAZON_SOCIAL;
        $newOrd->CLI_CORREO_ELECT_SRI       = $cliente->CLI_CORREO_ELECT_SRI;
        // Lógica de facturación
        $this->setBillingLogic($newOrd, $cliente);
        $newOrd->save();
        return $newOrd;
    }

    private function setBillingLogic($newO, $cliente)
    {
        $newO->factura_siguiente_mes = 0;
        if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $newO->factura_posterior_despacho = 1;

            if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $newO->factura_a_demanda = 1;
            } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $newO->factura_siguiente_mes = 1;
            }
        }
    }


    public function processDetailOrder(Collection $detailOrigin, GpPedido $newOrderHeader, GpPedido $orderOrigin, GpDevoluciones $devolucion, bool $isDispatch)
    {
        $impuestoArt = GpArticulo::whereIn('ART_CODIGO', $detailOrigin->pluck('ART_CODIGO'))->get()->keyBy('ART_CODIGO');

        foreach ($detailOrigin as $dtOr) {
            $cantidad = $this->calculateQuantity($dtOr, $devolucion, $isDispatch);
            if (!$cantidad)
                continue;
            $newDetail                  = $dtOr->replicate();
            $newDetail->pedido_id       = $newOrderHeader->id;
            $newDetail->devuelto        = null;
            $newDetail->ART_CANTIDAD    = $cantidad;
            $newDetail->ART_IMP_VTA     = $impuestoArt[$dtOr->ART_CODIGO]->ART_IMP_VTA;
            $newDetail->save();

            $this->replicateProviders($orderOrigin, $newOrderHeader, $dtOr, $newDetail);
            $this->replicateStock($orderOrigin, $newOrderHeader, $dtOr, $newDetail, $devolucion, $cantidad);
        }
        
    }



    public function calculateQuantity($detail, GpDevoluciones $devolucion, bool $isDispatch)
    {
        $artDevOrigin = GpDevolucionesDetalle::where('detalle_id', $detail->id)
            ->where('devolucion_id', $devolucion->id)->first();
        if (!$artDevOrigin)
            return null;

        if ($devolucion->tipo_devolucion == 'parcial') {
            if ($isDispatch) {
                return $artDevOrigin->disponible - $artDevOrigin->recibido;
            } else {
                return $artDevOrigin->disponible - $artDevOrigin->seleccionado;
            }
        } else {
            return $artDevOrigin->disponible;
        }
    }


    public function replicateProviders(GpPedido $orderOrigin, GpPedido $newOrder, GpPedidoDetalle $detailOrigin, GpPedidoDetalle $newDetail)
    {
        $providers = GpPedidoDetalleProveedores::where('pedido_id', $detailOrigin->id)
            ->where('detalle_id', $detailOrigin->id)->get();

        foreach ($providers as $prov) {
            $newProv                    = $prov->replicate();
            $newProv->pedido_id         = $newOrder->id;
            $newProv->pedido_detalle_id = $newDetail->id;
            $newProv->save();
            $this->transferInventoryGpComparment($newOrder, $newDetail, $orderOrigin, $newProv->CANTIDAD);
        }
    }

    public function transferInventoryGpComparment($nuevoPedido, $nuevoDet, $pedidoOriginal, $cantidad)
    {
        $parameters = [
            'fecha'     => date('Y/m/d'),
            'item'      => $nuevoDet->ART_CODIGO,
            'bodega'    => $nuevoPedido->BOD_BODEGA,
            'cantidad'  => $cantidad,
            'origen'    => $pedidoOriginal->pedido,
            'destino'   => $nuevoPedido->pedido,
        ];

        try {
            $resp = $this->api->transferCompartment($parameters);
            Log::channel('devolucion')->info('TransferenciaCompartimento', ['resp' => $resp, 'params' => $parameters]);
        } catch (\Exception $e) {
            Log::channel('devolucion')->error('Error TransferenciaCompartimento', ['error' => $e->getMessage()]);
            // Opcional: throw $e; si quieres cancelar toda la transacción
        }
    }


    public function replicateStock(GpPedido $orderOrigin, GpPedido $newOrder, GpPedidoDetalle $detailOrigin, GpPedidoDetalle $newDetail, GpDevoluciones $devolucion, $qty)
    {
        $stocks = GpPedidoDetalleStock::where('pedido_id', $orderOrigin->id)
            ->where('detalle_id', $detailOrigin->id)->get();

        foreach ($stocks as $st) {
            $nwSt               = $st->replicate();
            $nwSt->pedido_id    = $newOrder->id;
            $nwSt->detalle_id   = $newDetail->id;

            if ($devolucion->tipo_devolucion == 'parcial' && $nwSt->STO_ART_BODEGA == $orderOrigin->BOD_BODEGA) {
                $nwSt->seleccionado = $qty;
                $nwSt->reservado    = $qty;
            } elseif ($devolucion->tipo_devolucion == 'total') {
                $nwSt->reservado    = $nwSt->seleccionado;
            }
            $nwSt->save();
        }
    }
}
