<?php

namespace App\Services\Bodegas;

use App\Models\GpCliente;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;

class PurchaseReceiptLItemService implements PurchaseReceiptLItemServiceInterface
{

    protected $invoiceService;
    protected $notificacionService;
    protected $purechaseReceiptService;
    protected $purchaseReceipRepository;
    public function __construct(InvoiceServiceInterface $invoiceServiceInterface,
    NotificationServiceInterface $notificacionService,
    PurchaseReceiptServiceInterface $purechaseReceiptService,
    PurchaseReceipRepositoryInterface $purchaseReceipRepositoryInterface)
    {
        $this->invoiceService = $invoiceServiceInterface;
        $this->notificacionService = $notificacionService;
        $this->purechaseReceiptService = $purechaseReceiptService;
        $this->purchaseReceipRepository = $purchaseReceipRepositoryInterface;
    }

    public function checkOrderReception($orden, $detailOrder, $user)
    {
        $recibida = true;
        foreach ($detailOrder as $item) {
            $this->updateProviderDetail($item,  $recibida);
            $this->updateOrderDetail($item);
        }

        $ordenCompra = GpOrdenDeCompra::find($orden->orden_id);
        $orderModel = GpPedido::where('pedido', $ordenCompra->ORDEN_COMPRA_DOCU_ORIGEN)->first();
        if ($recibida) {
            $this->markOrderAsReceived($orden, $user->USERID);
            $this->proccessClientInvoice($orderModel);
            $this->invoiceService->checkSendInvoice($orderModel, true);
        }
        $message = 'Artículo/s L del pedido ' . $orderModel->pedido . ' han sido recibidos en la bodega ' . $orderModel->BOD_BODEGA;
        $this->notificacionService->sendNotification($user->user_id, 'Artículo/s L recibidos', $message);
    }


    public function updateProviderDetail($item, &$recibida)
    {
        $providerDetail = GpPedidoDetalleProveedores::find($item['detalle_proveedores_id']);
        if (!$providerDetail) {
            throw new \Exception('El detalle del proveedor no existe');
        }
        $providerDetail->ingresado = ($providerDetail->ingresado ?? 0) + $item['seleccionado'];
        $providerDetail->recibido = $providerDetail->CANTIDAD == $providerDetail->ingresado ? 'yes' : 'no';

        if ($providerDetail->recibido !== 'yes') {
            $recibida = false;
        }

        $providerDetail->save();
    }

    public function updateOrderDetail($item)
    {
        $orderDetail = GpOrdenDeCompraGeneradoDetalle::find($item['id']);
        if (!$orderDetail) {
            throw new \Exception('El detalle del proveedor no existe');
        }
        $orderDetail->recibido = ($orderDetail->recibido ?? 0) + $item['seleccionado'];
        $orderDetail->save();
    }

    public function markOrderAsReceived($order, $user)
    {
        $order->estado = 'recibido';
        $order->USERID = $user;
        $order->save();
    }

    public function proccessClientInvoice(Gppedido $order)
    {
        $client = GpCliente::find($order->cliente_id);
        if (!$client) {
            throw new \Exception('El cliente no existe');
        }
        if ($client->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $order->factura_posterior_despacho  = 1;

            $diaActual = now()->day;
            if ($client->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $order->factura_a_demanda = 1;
            } elseif ($diaActual > $client->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $order->factura_siguiente_mes  = 1;
            }
            $order->save();
        }
    }



    /** proceso de impresion de recibos de compra L  */

    public function printPurchaseReceiptL($id)
    {
        $data  = $this->purchaseReceipRepository->getPurchaseReceiptLById($id);
        if (!$data) {
            throw new \Exception('El recibo de compra no existe');
        }
        $this->getDetailPurchaseReceiptL($data);
        return $data;
    }




    public function getDetailPurchaseReceiptL($ordCG)
    {
        return $this->purechaseReceiptService->setPurcharseOrderdetails($ordCG);
    }

}
