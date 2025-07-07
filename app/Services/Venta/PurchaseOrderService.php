<?php

namespace App\Services\Venta;

use App\Models\GpPedido;
use App\Models\GpProveedor;
use App\Models\GpPedidoDetalle;
use App\Services\Api\MswService;
use Illuminate\Support\Facades\DB;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;

class PurchaseOrderService implements PurchaseOrderServiceInterface
{
    const ORDER_STATE = 'orden_de_compra';
    const ORDER_STATUS = 'iniciado';
    const SENT_STATUS = 'enviado';
    const GENERATED_STATUS = 'generado';
    protected $mswService;
    public function __construct(MswService $mswService)
    {
        $this->mswService = $mswService;
    }

    public function getViewPurchaseOrder()
    {
        return DB::table('tuvalapi.view_ordenes_compra')->get();
    }

    public function getOrdersDispatchPending()
    {
        return DB::table('tuvalapi.vw_OrdenesDespachoPendientesCxC')->get();
    }


    /** funciones anidadas para el check de envio de factura */



    public function checkPurchaseOrder(GpPedido $pedido)
    {

        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->where('ART_TIPO_COMPRA', 'L')->get();
        if ($detalles->isEmpty()) {
            return;
        }

        $ordenCreado = false;
        $lineNumber = 0;

        foreach ($detalles as $detalle) {
            $proveedores = GpPedidoDetalleProveedores::where('gp_pedido_detalle_proveedores.detalle_id', $detalle->id)
                ->leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.detalle_proveedores_id', 'gp_pedido_detalle_proveedores.id')
                ->where('recibido', 'no')
                ->whereNotExists(function ($query) {
                    $query->select('gp_orden_de_compras.id')
                        ->from('gp_orden_de_compras')
                        ->whereColumn('gp_orden_de_compras.detalle_proveedores_id', '=', 'gp_pedido_detalle_proveedores.id');
                })->select('gp_pedido_detalle_proveedores.*')->get();
            if ($proveedores->isEmpty()) {
                $lineNumber++;
                continue;
            }

            $provedoresIds = $proveedores->pluck('ID_PROVEEDOR')->unique();
            $proveedoresData = GpProveedor::whereIn('id', $provedoresIds)->get()->keyBY('id');

            $subLineNumber = 1;

            foreach ($proveedores as $proveedor) {
                $ordenCreado = true;

                $proveedorData = $proveedoresData->get($proveedor->ID_PROVEEDOR);
                if (!$proveedorData) {
                    continue;
                }

                GpOrdenDeCompra::create([
                    'detalle_id' => $detalle->id,
                    'detalle_proveedores_id' => $proveedor->id,
                    'estado' => self::ORDER_STATUS,
                    'ORDEN_COMPRA_DOCU_ORIGEN' => $pedido->pedido,
                    'ORDEN_COMPRA_LINEA' => "{$lineNumber}-" . str_pad($subLineNumber, 2, "0", STR_PAD_LEFT),
                    'ORDEN_COMPRA_FECHA_DOCUMENTO' => $pedido->created_at,
                    'ORDEN_COMPRA_FECHA_ENTREGA' => $proveedor->FECHA_ENTREGA . ' 12:00:00',
                    'ORDEN_COMPRA_ID_CLIENTE' => $pedido->CLI_RUC,
                    'ORDEN_COMPRA_ID_PROVEEDOR' => $proveedorData->PROV_RUC,
                    'ORDEN_COMPRA_ID_DIRECCION' => $proveedorData->PROV_ID_DIRECCION,
                    'ORDEN_COMPRA_NOTA_DOCU' => $proveedor->NOTA,
                    'ORDEN_COMPRA_BODEGA' => $pedido->BOD_BODEGA,
                    'ORDEN_COMPRA_ARTICULO' => $detalle->ART_CODIGO,
                    'ORDEN_COMPRA_DESCRIPCION_ARTICULO' => $proveedor->DESCRIPCION_ARTICULO,
                    'ORDEN_COMPRA_UMEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                    'ORDEN_COMPRA_CANTIDAD' => $proveedor->CANTIDAD,
                    'ORDEN_COMPRA_COSTO_UNITARIO' => $proveedor->COSTO_UNITARIO,
                    'ORDEN_COMPRA_TOTAL' => $proveedor->TOTAL,
                    'ORDEN_COMPRA_IMPUESTO' => $proveedor->IMPUESTO,
                    'ORDEN_COMPRA_ID_USUARIO' => $pedido->USERID,
                ]);
                $subLineNumber++;

            }
            $lineNumber++;
        }

        if ($ordenCreado) {
            $pedido->estado = self::ORDER_STATE;
            $pedido->save();
        }

    }


    public function sendPurchaseOrders()
    {
        $orders = $this->getPendingPurchaseOrders();
        if ($orders->isEmpty()) {
            return;
        }
        $ordersByProvider = $this->groupOrdersByProviders($orders);
        foreach ($ordersByProvider as $providerId => $providerOrders) {
            $this->sendOrdersForProvider($providerOrders);
        }

        // finalmente procesar las generadas
        foreach ($ordersByProvider as $providerOrders) {
            $this->generarOrdenCompra($providerOrders);
        }
    }

    public function getPendingPurchaseOrders()
    {
        return GpOrdenDeCompra::where('estado', self::ORDER_STATUS)->get();
    }
    public function groupOrdersByProviders($orders)
    {
        return $orders->groupBy('ORDEN_COMPRA_ID_PROVEEDOR')->toArray();
    }

    public function sendOrdersForProvider($providerOrders)
    {
        $header = array_map(fn($order) => $this->buildHeaderForOrder($order), $providerOrders);
        $parmas = $header[0];
        $this->mswService->transaccional('MST_Orden_Compra', array('Cabecera' => $parmas));
        $firstOrder = $providerOrders[0];

        $response = $this->mswService->transaccional('MST_Respuesta_Orden_Compra', array('documento_origen' => $firstOrder['ORDEN_COMPRA_DOCU_ORIGEN'], 'linea' => $firstOrder['ORDEN_COMPRA_LINEA']));
        $this->processResponse($providerOrders, $response);


    }

    public function buildHeaderForOrder($order)
    {
        return [
            'ORDEN_COMPRA_DOCU_ORIGEN' => $order['ORDEN_COMPRA_DOCU_ORIGEN'],
            'ORDEN_COMPRA_LINEA' => $order['ORDEN_COMPRA_LINEA'],
            'ORDEN_COMPRA_FECHA_DOCUMENTO' => $order['ORDEN_COMPRA_FECHA_DOCUMENTO'],
            'ORDEN_COMPRA_FECHA_ENTREGA' => $order['ORDEN_COMPRA_FECHA_ENTREGA'],
            'ORDEN_COMPRA_ID_CLIENTE' => $order['ORDEN_COMPRA_ID_CLIENTE'],
            'ORDEN_COMPRA_ID_PROVEEDOR' => $order['ORDEN_COMPRA_ID_PROVEEDOR'],
            'ORDEN_COMPRA_ID_DIRECCION' => $order['ORDEN_COMPRA_ID_DIRECCION'],
            'ORDEN_COMPRA_NOTA_DOCU' => $order['ORDEN_COMPRA_NOTA_DOCU'],
            'ORDEN_COMPRA_BODEGA' => $order['ORDEN_COMPRA_BODEGA'],
            'ORDEN_COMPRA_ARTICULO' => $order['ORDEN_COMPRA_ARTICULO'],
            'ORDEN_COMPRA_DESCRIPCION_ARTICULO' => $this->sanitizeDescription($order['ORDEN_COMPRA_DESCRIPCION_ARTICULO']),
            'ORDEN_COMPRA_UMEDIDA' => $order['ORDEN_COMPRA_UMEDIDA'],
            'ORDEN_COMPRA_CANTIDAD' => $order['ORDEN_COMPRA_CANTIDAD'],
            'ORDEN_COMPRA_COSTO_UNITARIO' => $order['ORDEN_COMPRA_COSTO_UNITARIO'],
            'ORDEN_COMPRA_TOTAL' => $order['ORDEN_COMPRA_TOTAL'],
            'ORDEN_COMPRA_IMPUESTO' => $order['ORDEN_COMPRA_IMPUESTO'],
            'ORDEN_COMPRA_ID_USUARIO' => $order['ORDEN_COMPRA_ID_USUARIO'],
        ];
    }
    public function sanitizeDescription(string $description): string
    {
        $clean = str_replace(['*', '`', '  ', ';'], ['', '', ' ', '-'], $description);
        return substr($clean, 0, 99);
    }

    public function processResponse($orders, $response)
    {
        $result = $response['MST_Respuesta_Orden_CompraResult']['CS_RESPUESTA_ORDEN_COMPRA'][0] ?? [];

        if (!empty($result['DOC_GENERADO'])) {
            $this->updateOrdersAsSent($orders, $result['DOC_GENERADO']);
        } else {
            $this->updateOrdersWithError($orders, $result);
        }
    }

    public function updateOrdersAsSent($orders, $docGenerated)
    {
        foreach ($orders as $order) {
            GpOrdenDeCompra::where('id', $order['id'])->update(['estado' => self::SENT_STATUS, 'DOC_GENERADO' => $docGenerated]);
        }
    }
    public function updateOrdersWithError($orders, $result)
    {
        $message = ($result['ORDEN_COMPRA_CODIGO'] ?? '') . ' - ' . ($result['ORDEN_COMPRA_MENSAJE'] ?? '');
        foreach ($orders as $order) {
            GpOrdenDeCompra::where('id', $order['id'])->update([
                'ORDEN_COMPRA_MENSAJE' => $message,
            ]);
        }
    }



    /**
     * Summary of generarOrdenCompra
     * @param mixed $orden
     * @return void
     */

    public function generarOrdenCompra($order)
    {
        if (empty($order) || empty($order[0]['DOC_GENERADO'])) {

        }
        $documentoOrtigen = $order[0]['DOC_GENERADO'];
        $result = $this->callSoapGeneratePurchaseOrder($documentoOrtigen);
        if (!$result) {
            return;
        }
        $header = $this->saveGeneratedOrderheader($order, $result);
        $this->saveGenerayedOrderDetails($header, $order, $result);
    }

    public function callSoapGeneratePurchaseOrder($documentoOrtigen)
    {
        $params = ['documento_origen' => $documentoOrtigen];
        $response = $this->mswService->transaccional('MST_Generacion_Orden_Compra', $params);
        return $response['MST_Generacion_Orden_CompraResult']['CS_GENERACION_ORDEN_COMPRA'][0] ?? null;
    }

    public function saveGeneratedOrderheader($order, $generated)
    {
        return GpOrdenDeCompraGenerado::create([
            'orden_id' => $order[0]['id'],
            'detalle_id' => $order[0]['detalle_id'],
            'detalle_proveedores_id' => $order[0]['detalle_proveedores_id'],
            'estado' => self::GENERATED_STATUS,
            'ORDEN_COMPRA_DOCUMENTO' => $generated['ORDEN_COMPRA_DOCUMENTO'] ?? '',
            'ORDEN_COMPRA_FECHA_DOCUMENTO' => $this->parseSoapDate($generated['ORDEN_COMPRA_FECHA_DOCUMENTO']),
            'ORDEN_COMPRA_FECHA_ENTREGA' => $this->parseSoapDate($generated['ORDEN_COMPRA_FECHA_ENTREGA']),
            'ORDEN_COMPRA_ID_PROVEEDOR' => $generated['ORDEN_COMPRA_ID_PROVEEDOR'] ?? '',
            'ORDEN_COMPRA_DIRECCION' => $generated['ORDEN_COMPRA_DIRECCION'] ?? '',
            'ORDEN_COMPRA_NOTA_DOCU' => $generated['ORDEN_COMPRA_NOTA_DOCU'] ?? '',
            'recibidos' => 1,
        ]);
    }

    public function parseSoapDate($soapDate): ?string
    {
        return is_array($soapDate) && isset($soapDate['date']) ? $soapDate['date'] : null;
    }

    public function saveGenerayedOrderDetails($header, $order, $generated)
    {
        $details = $generated['ORDEN_COMPRA_DETALLE']['CS_GENERACION_ORDEN_COMPRA_DETALLE'] ?? [];
        foreach ($details as $detail) {
            $this->saveGeneratedOrderDetail($header, $order, $detail);
        }
    }

    public function saveGeneratedOrderDetail($header, $order, $detail)
    {
        $matchingDetailId = $this->findMatchingDetailId($order, trim($detail['ORDEN_COMPRA_DETALLE_ARTICULO']));

        GpOrdenDeCompraGeneradoDetalle::create([
            'orden_generada_id' => $header->id,
            'detalle_proveedores_id' => $matchingDetailId,
            'ORDEN_COMPRA_DETALLE_BODEGA' => $detail['ORDEN_COMPRA_DETALLE_BODEGA'] ?? '',
            'ORDEN_COMPRA_DETALLE_ARTICULO' => $detail['ORDEN_COMPRA_DETALLE_ARTICULO'] ?? '',
            'ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO' => $detail['ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO'] ?? '',
            'ORDEN_COMPRA_DETALLE_UMEDIDA' => $detail['ORDEN_COMPRA_DETALLE_UMEDIDA'] ?? '',
            'ORDEN_COMPRA_DETALLE_CANTIDAD' => $detail['ORDEN_COMPRA_DETALLE_CANTIDAD'] ?? 0,
            'ORDEN_COMPRA_DETALLE_COSTO_UNITARIO' => $detail['ORDEN_COMPRA_DETALLE_COSTO_UNITARIO'] ?? 0,
            'ORDEN_COMPRA_DETALLE_TOTAL' => $detail['ORDEN_COMPRA_DETALLE_TOTAL'] ?? 0,
            'ORDEN_COMPRA_DETALLE_IMPUESTO' => $detail['ORDEN_COMPRA_DETALLE_IMPUESTO'] ?? 0,
        ]);

        $this->markOrderAsGenerated($order, $detail['ORDEN_COMPRA_DETALLE_ARTICULO']);
    }

    public function findMatchingDetailId($order, $articulo)
    {
        foreach ($order as $ord) {
            if ($ord['ORDE_COMPRA_ARTICULO'] == $articulo) {
                return $ord['detalle_proveedores_id'];
            }
        }
        return null;
    }
    public function markOrderAsGenerated($order, $articulo)
    {
        foreach ($order as $ord) {
            if ($ord['ORDE_COMPRA_ARTICULO'] == $articulo) {
                GpOrdenDeCompra::where('id', $ord['id'])->update(['estado' => self::GENERATED_STATUS]);
            }
        }
    }

    public function checkPurchaseOrderRecib(GpPedido $pedido)
    {

        $hasPending = GpPedidoDetalleProveedores::whereHas('pedidodetalle', function ($query) use ($pedido) {
            $query->where('pedido_id', $pedido->id)
                ->where('ART_TIPO_COMPRA', 'L');

        })->where('recibido', 'no')->exists();

        if ($hasPending) {
            $pedido->estado = self::ORDER_STATE;
            $pedido->save();
        }

        return $hasPending;
    }


}
