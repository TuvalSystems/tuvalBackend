<?php

namespace App\Services\Compras\Recepciones;

use App\Jobs\Bodegas\NotifyPurchaseReceiptItemLJob;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalleProveedores;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompra;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompraGeneradoDetalle;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use App\Services\Integraciones\Api\Transacciones\ApiGpReceivedItemL;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Facturacion\Contracts\InvoiceServiceInterface;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptLItemServiceInterface;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class PurchaseReceiptLItemService implements PurchaseReceiptLItemServiceInterface
{

    public function __construct(
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly PurchaseReceiptServiceInterface $purechaseReceiptService,
        protected readonly PurchaseReceipRepositoryInterface $purchaseReceipRepository,
        protected readonly ApiGpReceivedItemL $apiGpReceiptService,
        protected readonly EntryServiceInterface $entryService,
    ) {

    }

    /** Proceso de recibir item L en la bodega */

    public function receivedL(array $data, GpUsuario $user)
    {
        $ordCompra = $this->purchaseReceipRepository->getPurchaseReceiptLById($data['id']);
        $hasPending = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $ordCompra->id)
            ->whereRaw('ORDEN_COMPRA_DETALLE_CANTIDAD > COALESCE(recibido, 0)')
            ->exists();
        $header = $this->formatHeaderPurcharseOrder($ordCompra);

        $items = array_values(array_filter($data['detalle'], function ($d) {
            $v = $d['seleccionado'] ?? null;
            return is_numeric($v) && (float) $v > 0;
        }));


        if (empty($items)) {
            throw new \DomainException('No hay ítems seleccionados para recibir.');
        }

        $this->assertDuplicateArticlesByName($items);

        $detail = $this->formatDetailPurcharseOrder($ordCompra, $items);
        $valHdr = $this->formatHeaderValidation($ordCompra);
        $resValidate = $this->apiGpReceiptService->validateGenerationReceivedItemL($valHdr);

        $successValidate = (bool) ($resValidate['success'] ?? false);
        $dataValidate = $resValidate['data'] ?? [];
        $codigo = (string) ($dataValidate['codigo'] ?? '');

        if ($successValidate && $codigo != '0') {
            Log::channel('apiservice')->error("funcion receivedL, validacion fallida:", [
                'res' => $dataValidate
            ]);
            throw new \RuntimeException("Error al validar el recibo de compra");
        }

        if ($successValidate && $codigo === '0' && !$hasPending) {
            Log::channel('apiservice')->error("Ya existe un documento en Dynamics GP :", [
                'res' => $dataValidate
            ]);
            throw new \RuntimeException("Ya existe un documento en Dynamic GP para la orden {$ordCompra->ORDEN_COMPRA_DOCUMENTO}_{$ordCompra->recibidos}");
        }

        if (!$successValidate) {
            Log::channel('apiservice')->error("Mensaje de existencia de Recibo de compra :", [
                'res' => $dataValidate
            ]);
            $respDelete = $this->apiGpReceiptService->deleteReceivedItemL($valHdr);
            Log::channel('apiservice')->error("Eliminacion de recibo de compra : ", [
                'res' => $respDelete
            ]);
            $payload = [
                'cabecera' => $header,
                'detalle' => $detail
            ];
            $respStoreL = $this->apiGpReceiptService->saveReceivedItemL($payload);
            $successStore = (bool) ($respStoreL['success'] ?? false);
            Log::channel('apiservice')->error("Guardado de recibo de compra : ", [
                'res' => $respStoreL
            ]);
            if ($successStore) {
                $this->apiGpReceiptService->generateReceivedItemL($valHdr);
            }
            $resultRcOrdG = $this->apiGpReceiptService->responseReceivedItemL($valHdr);

            $successRcOrd = (bool) ($resultRcOrdG['success'] ?? false);
            $resRcOrd = $resultRcOrdG['data'] ?? [];
            // $mensajeRcOrd = (string)($resRcOrd['message'] ? $resultRcOrdG['message'] : 'Error desconocido');
            if ($successRcOrd) {
                if ($resRcOrd['codigo'] === '0' && $resRcOrd['numDocGenerado'] != '') {
                    $ordCompra->ORDEN_COMPRA_RECIBO .= '_' . $resRcOrd['numDocGenerado'];
                    $ordCompra->increment('recibidos');
                    $ordCompra->refresh();
                    $this->checkOrderReception($ordCompra, $data['detalle'], $user);
                }
                if ($resRcOrd['codigo'] != '0') {
                    throw new \RuntimeException("Error al contabilizar el recibo de compra");
                }
            } else {
                throw new \RuntimeException("El recibo de orden de compra aun no ha sido contabilizado<br>Recibir la orden de compra en un minuto");
            }
        } else {
            $resultRcOrdG = $this->apiGpReceiptService->responseReceivedItemL($valHdr);
            $successRcOrd = (bool) ($resultRcOrdG['success'] ?? false);
            $resRcOrd = $resultRcOrdG['data'] ?? [];

            if ($successRcOrd) {
                if ($resRcOrd['codigo'] === '0' && $resRcOrd['numDocGenerado'] != '') {
                    $ordCompra->ORDEN_COMPRA_RECIBO .= '_' . $resRcOrd['numDocGenerado'];
                    $ordCompra->increment('recibidos');
                    $ordCompra->refresh();
                    $this->checkOrderReception($ordCompra, $data['detalle'], $user);
                }
                if ($resRcOrd['codigo'] != '0') {
                    // throw new \RuntimeException("Error al contabilizar el recibo de compra: {$msjRcOrdG}");
                    throw new \RuntimeException("Error al contabilizar el recibo de compra");
                }
            }
        }
        $this->entryService->registerEntity('compra', $data, $data['detalle'], $user);
    }


    public function assertDuplicateArticlesByName($items, $field = 'ORDEN_COMPRA_DETALLE_ARTICULO')
    {
        $dups = collect($items)
            ->map(function ($item) use ($field) {
                $raw = data_get($item, $field);
                $n = is_string($raw) ? trim(preg_replace('/\s+/u', ' ', $raw)) : '';
                return $n;
            })->filter()
            ->countBy()
            ->filter(fn($c) => $c > 1);

        if ($dups->isNotEmpty()) {
            $lista = $dups->map(function ($count, $normalized) {
                $pretty = ucwords($normalized);
                return "{$pretty} (x{$count})";
            })->values()->implode(', ');
            throw new \DomainException("Hay artículos repetidos por ORDEN_COMPRA_DETALLE_ARTICULO: {$lista}");
        }
    }

    public function checkOrderReception($orden, $detailOrder, $user)
    {
        $recibida = true;
        foreach ($detailOrder as $item) {
            $this->updateProviderDetail($item, $recibida);
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
        NotifyPurchaseReceiptItemLJob::dispatch('Articulo/s L recibidos', $message, $user->id);

        // $gpUser = GpUsuario::where('USERID', $user->USERID)->first();
        // if ($gpUser) {
        //     $gpUser->notify(new UserNotification('Articulo/s L recibidos', $message));
        // }

    }


    public function updateProviderDetail($item, &$recibida)
    {
        $providerDetail = GpPedidoDetalleProveedores::find($item['detalle_proveedores_id']);
        if (!$providerDetail) {
            return;
            // throw new \Exception('El detalle del proveedor no existe');
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
            return;
            // throw new \Exception('El detalle del proveedor no existe');
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

    public function proccessClientInvoice(GpPedido $order)
    {
        $client = GpCliente::find($order->cliente_id);
        if (!$client) {
            throw new \Exception('El cliente no existe');
        }
        if ($client->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $order->factura_posterior_despacho = 1;

            $diaActual = now()->day;
            if ($client->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $order->factura_a_demanda = 1;
            } elseif ($diaActual > $client->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $order->factura_siguiente_mes = 1;
            }
            $order->save();
        }
    }

    public function formatHeaderValidation($order)
    {
        return [
            'documento' => $order->ORDEN_COMPRA_DOCUMENTO . '_' . $order->recibidos,
            'tipo' => 1,
        ];
    }

    public function formatHeaderPurcharseOrder($order)
    {
        return [
            'REC_COM_DOCU_ORIGEN' => $order->ORDEN_COMPRA_DOCUMENTO . '_' . $order->recibidos,
            'REC_COM_ID_TIPO' => 1,
            'REC_COM_ID_PROVEEDOR' => $order->ORDEN_COMPRA_ID_PROVEEDOR,
            'REC_COM_FECHA' => date('Y-m-d'),
        ];
    }

    public function formatDetailPurcharseOrder($ord, $detalle)
    {
        $docOrigen = $ord->ORDEN_COMPRA_DOCUMENTO . '_' . $ord->recibidos;
        $detalle = array_filter($detalle, fn($d) => (float) Arr::get($d, 'seleccionado', 0) > 0);
        return array_values(array_map(function ($d) use ($docOrigen) {
            return [
                'REC_COM_DOCU_ORIGEN' => $docOrigen,
                'REC_COM_ID_TIPO' => 1,
                'REC_COM_ID_ARTICULO' => Arr::get($d, 'ORDEN_COMPRA_DETALLE_ARTICULO'),
                'REC_COM_DESCRIPCION_ARTICULO' => Arr::get($d, 'ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO'),
                'REC_COM_UNIDAD_MEDIDA' => Arr::get($d, 'ORDEN_COMPRA_DETALLE_UMEDIDA'),
                'REC_COM_BODEGA' => Arr::get($d, 'ORDEN_COMPRA_DETALLE_BODEGA'),
                'REC_COM_CANTIDAD' => (float) Arr::get($d, 'seleccionado', 0),
            ];
        }, $detalle));
    }


    /** proceso de impresion de recibos de compra L  */

    public function printPurchaseReceiptL($id)
    {
        $data = $this->purchaseReceipRepository->getPurchaseReceiptLById($id);
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
