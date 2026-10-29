<?php

namespace App\Services\Devoluciones;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\OrderStatus;
use App\Models\Devoluciones\GpDevoluciones;
use App\Models\Devoluciones\GpDevolucionesDetalle;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Inventario\Despachos\TipoDespacho;
use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Pedidos\GpPedidoDetalleProveedores;
use App\Models\Ventas\Pedidos\GpPedidoDetalleStock;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Repositories\Devoluciones\DevolucionRepository;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Compartido\Contracts\FunctionsServiceInterface;
use App\Services\Facturacion\Contracts\InvoiceServiceInterface;
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
    ) {}



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
        $userOrder = GpUsuario::where('id', $newOrderHeader->user_id)->first();
        //notificar al usuario
        $mensaje = "El pedido {$newOrderHeader->pedido} ha sido creado exitosamente tras el proceso de refacturación por devolución.";
        $userOrder->notify(new UserNotification('Pedido Refacturado', $mensaje));

        return $newOrderHeader->refresh();
    }



    public function createOrderHeader(GpDevoluciones $devolucion, GpPedido $orderOrigin, bool $isDispatch, GpCliente $cliente)
    {
        $newOrd                             = $orderOrigin->replicate();
        // Preserve the original freight agreement when issuing the replacement invoice.
        $newOrd->fill($orderOrigin->only([
            'tipo_despacho_id', 'responsable_flete', 'fle_proveedor_id',
            'peso', 'fle_peso', 'fle_valor', 'fle_usuario_id', 'fle_fecha',
            'fle_usuario_aut_id', 'fle_fecha_aut',
        ]));
        $newOrd->pedido                     = null; //esto lo crea el observer que generamos (se crea automaticamente)
        $newOrd->factura                    = null;
        $newOrd->factura_origen             = $orderOrigin->factura;
        $newOrd->fecha_factura              = null;
        $newOrd->estado                     = OrderStatus::ESPERA;
        $newOrd->despachado                 = $isDispatch;
        $newOrd->sucursal_id                = $devolucion->sucursal_id;
        $newOrd->sucursal_facturacion_id    = $devolucion->sucursal_facturacion_id;
        $newOrd->cliente_id                 = $devolucion->cliente_volver_a_facturar_id;
        $newOrd->orden_de_compra            = $devolucion->orden_de_compra;
        $newOrd->nota_cliente               = $devolucion->nota_cliente;
        $newOrd->CLI_RUC                    = $cliente->CLI_RUC;
        $newOrd->CLI_RAZON_SOCIAL           = $cliente->CLI_RAZON_SOCIAL;
        $newOrd->CLI_CORREO_ELECT_SRI       = $cliente->CLI_CORREO_ELECT_SRI;
        $newOrd->origen                     = OrderOrigenEnum::DEVOLUCION->value;
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
        $hasPackageService = $detailOrigin->contains(fn ($detail) => $detail->es_servicio_logistico)
            && (new ReturnFreightPolicy())->dispatchType(TipoDespacho::find($orderOrigin->tipo_despacho_id)?->descripcion) === 'paqueteria';
        $copyPackageService = $hasPackageService
            && $devolucion->tipo_devolucion === 'total'
            && (bool) $devolucion->volver_a_facturar
            && ((!GpGuiaRemision::where('pedido', $orderOrigin->pedido)->exists())
                || ($isDispatch && (bool) $orderOrigin->despachado));

        foreach ($detailOrigin as $dtOr) {
            // Total replacement preserves service before dispatch or after full dispatch, including guides with OCs.
            $cantidad = $hasPackageService && $dtOr->es_servicio_logistico
                ? ($copyPackageService ? (float) $dtOr->ART_CANTIDAD : 0)
                : $this->calculateQuantity($dtOr, $devolucion, $isDispatch);
            if ($cantidad <= 0) {
                continue;
            }
            $newDetail                  = $dtOr->replicate();
            $newDetail->fill($dtOr->only([
                'art_id', 'fle_valor', 'precio_sin_flete', 'lista_precio', 'es_servicio_logistico',
            ]));
            $newDetail->pedido_id       = $newOrderHeader->id;
            $newDetail->devuelto        = 0;
            $newDetail->ART_CANTIDAD    = $cantidad;
            $newDetail->ART_IMP_VTA     = $impuestoArt[$dtOr->ART_CODIGO]->ART_IMP_VTA;
            $newDetail->save();

            if ($newDetail->es_servicio_logistico) {
                // This line must be invoiced again, but has no physical stock to transfer.
                continue;
            }
            $this->replicateProviders($orderOrigin, $newOrderHeader, $dtOr, $newDetail);
            $this->replicateStock($orderOrigin, $newOrderHeader, $dtOr, $newDetail, $devolucion, $cantidad);
        }
    }



    public function calculateQuantity($detail, GpDevoluciones $devolucion, bool $isDispatch)
    {
        $artDevOrigin = GpDevolucionesDetalle::where('detalle_id', $detail->id)
            ->where('devolucion_id', $devolucion->id)->first();
        if (!$artDevOrigin) {
            return 0;
        }
        $quantity = $artDevOrigin->disponible;

        if ($devolucion->tipo_devolucion == 'parcial') {
            $deduction = $isDispatch ? $artDevOrigin->recibido : $artDevOrigin->seleccionado;
            $quantity -= $deduction;
        }
        return max(0, $quantity);
    }


    public function replicateProviders(GpPedido $orderOrigin, GpPedido $newOrder, GpPedidoDetalle $detailOrigin, GpPedidoDetalle $newDetail)
    {
        $providers = GpPedidoDetalleProveedores::where('pedido_id', $orderOrigin->id)
            ->where('detalle_id', $detailOrigin->id)->get();
        if ($providers->isEmpty()) {
            return;
        }
        foreach ($providers as $prov) {
            $newProv                    = $prov->replicate();
            $newProv->pedido_id         = $newOrder->id;
            $newProv->detalle_id        = $newDetail->id;
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
        if ($stocks->isEmpty()) {
            return;
        }
        foreach ($stocks as $st) {
            if ($qty <= 0) {
                break;
            }
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
