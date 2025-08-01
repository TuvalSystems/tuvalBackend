<?php

namespace App\Services\Funciones;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Api\MswService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Log;

class InvoiceService implements InvoiceServiceInterface
{
    const STATUS_PENDING = 'pendiente';
    const STATUS_RELEASED = 'liberada';
    use JsonResponseTrait;
    protected $purchaseOrderService;
    protected $functionService;
    protected $mswService;
    // Implementa todos los metodos de facturacion de pedidos, devoluciones, aprobaciones, etc

    public function __construct(PurchaseOrderServiceInterface $purchaseOrderService, FunctionsServiceInterface $functionService, MswService $mswService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
        $this->functionService = $functionService;
        $this->mswService = $mswService;
    }

    /** Funcion de checkenviarfactura pasada aqui para reutilizar en otros servicios
     *  y que no se me cuelguen los metodos
     */

    public function checkSendInvoice(GpPedido $pedido, $trasladado = false, $checkDemanda = true)
    {
        if ($this->shouldSkipProcessing($pedido)) {
            return;
        }

        if ($this->shouldSkipDueToTransferStatus($pedido, $trasladado)) {
            return;
        }

        if ($this->processPurchaseOrders($pedido)) {
            return;
        }

        if ($checkDemanda && $this->checkInvoiceOnDemand($pedido)) {
            return;
        }

        $this->generateDisptachOrder($pedido);
        $this->functionService->recalculateOrder($pedido->id);
        $this->sendInvoiceGp($pedido);
        return;
    }

    /** funciones anidadas a  checkSendInvoice*/
    public function shouldSkipProcessing($order)
    {
        return in_array($order->estado, ['rechazado', 'anulada'], true) ||
            ($order->requiere_aprobacion_comercial == 'yes' && $order->aprobado_comercial == 'no') ||
            ($order->requiere_aprobacion_crediticia == 'yes' && $order->aprobado_crediticio == 'no');
    }

    public function shouldSkipDueToTransferStatus(GpPedido $pedido, $trasladado): bool
    {
        return $trasladado
            ? $this->checkTrasladado($pedido)
            : $this->checkTraslado($pedido);
    }

    public function processPurchaseOrders(GpPedido $pedido)
    {
        $this->purchaseOrderService->checkPurchaseOrder($pedido);
        $this->purchaseOrderService->sendPurchaseOrders();
        $this->purchaseOrderService->checkPurchaseOrderRecib($pedido);
        return in_array($pedido->estado, ['orden_de_compra', 'traslado'], true);
    }



    public function checkTrasladado(GpPedido $pedido)
    {
        $tieneTraslado = $this->getDataCheckTraslado($pedido);

        $pedido->estado = $tieneTraslado ? 'traslado' : 'espera';
        $pedido->save();
        return $tieneTraslado;
    }


    public function getDataCheckTraslado(GpPedido $pedido): bool
    {
        return GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA', '!=', $pedido->BOD_BODEGA)->exists();
    }

    public function checkTraslado(GpPedido $pedido)
    {
        $hayTraslado = $this->getDataCheckTraslado($pedido);
        $detalles = $this->getDetailForTraslate($pedido);
        $nuevoEstado = $hayTraslado ? 'traslado' : 'espera';
        $pedido->estado = $nuevoEstado;

        if ($nuevoEstado == 'traslado') {
            $this->saveTransferenciasVentas($pedido, $detalles);
        }
        $pedido->save();
        return $hayTraslado;
    }

    public function getDetailForTraslate(GpPedido $pedido)
    {
        return GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            ->whereNull('traslado')
            ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA', '!=', $pedido->BOD_BODEGA)
            ->select([
                'gp_pedido_detalles.*',
                'gp_pedido_detalle_stocks.STO_ART_BODEGA',
                'gp_pedido_detalle_stocks.seleccionado',
                'gp_pedido_detalle_stocks.id AS detalle_stocks_id'
            ])->get();
    }

    /**
     *  remplaza a la funcion savetransferenciasventas que esta en TrasnferenciasController
     */

    public function saveTransferenciasVentas(GpPedido $pedido, $detalles)
    {
        foreach ($detalles as $detalle) {
            if ($this->requiresTransfer($pedido, $detalle)) {
                GpTrasladosVentas::create($this->mapTransferenciaData($pedido, $detalle));
                $this->markDetalleAsTrasnfered($detalle);
            }
        }
    }

    public function requiresTransfer(GpPedido $pedido, $detalle)
    {
        return $pedido->BOD_BODEGA != $detalle->STO_ART_BODEGA;
    }

    public function mapTransferenciaData(GpPedido $pedido, $detalle)
    {
        return [
            'pedido_id' => $pedido->id,
            'detalle_id' => $detalle->id,
            'detalle_stocks_id' => $detalle->detalle_stocks_id,
            'ART_CODIGO' => $detalle->ART_CODIGO,
            'ART_DESCRIPCION' => $detalle->ART_DESCRIPCION_ORIGINAL,
            'ART_CODIGO_TRES_LETRAS' => $detalle->ART_CODIGO_TRES_LETRAS,
            'ART_PLAN_UMEDIDA' => $detalle->ART_PLAN_UMEDIDA,
            'ART_PESO' => $detalle->ART_PESO,
            'cantidad' => $detalle->seleccionado,
            'bodega_origen' => $detalle->STO_ART_BODEGA,
            'bodega_destino' => $pedido->BOD_BODEGA,
        ];
    }

    public function markDetalleAsTrasnfered(GpPedidoDetalle $detalle)
    {
        $detalle->traslado = 1;
        $detalle->save();
    }

    public function checkInvoiceOnDemand(GpPedido $pedido)
    {
        $this->setInvoiceFlags($pedido);
        $pedido->save();
        return $this->processInvoiceDecision($pedido);
    }

    public function setInvoiceFlags(GpPedido $pedido)
    {
        $cliente = GpCliente::where('id', $pedido->cliente_id)->first();
        if (!$cliente) {
            throw new \Exception('El cliente no existe');
        }

        $pedido->factura_posterior_despacho = ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO === 'SI') ? 1 : 0;
        if ($pedido->factura_posterior_despacho) {
            $hoy = (int) date('d');
            $diaMax = (int) $cliente->CLI_DIA_MAXIMO_MES_FACTURACION;
            if ($diaMax == 1) {
                $pedido->factura_a_demanda = 1;
            } elseif ($hoy > $diaMax) {
                $pedido->factura_siguiente_mes = 1;
            }
        }
    }

    public function processInvoiceDecision($pedido)
    {
        if (!$pedido->factura_posterior_despacho) {
            return false;
        }

        // facturr al dia menor al limite
        if (!$pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
            return false;
        }

        //caso factura a demanda
        if ($pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
            $this->generateDisptachOrder($pedido);
            return true;
        }

        //Facturar al siguiente mes
        if (!$pedido->factura_a_demanda && $pedido->factura_siguiente_mes) {
            $this->generateDisptachOrder($pedido);
            return true;
        }

        return false;
    }

    public function generateDisptachOrder($pedido)
    {
        try {
            if ($this->checkTrasladado($pedido)) {
                return;
            }
            if ($this->shouldSkipOrderGeneration($pedido)) {
                return;
            }

            if ($this->checkForExistingDispatchOrder($pedido)) {
                return;
            }

            [$estado, $fechaLiberacion] = $this->determineDispatchStatusAndDate($pedido);
            $this->storeNewDispatchOrder($pedido,  $estado, $fechaLiberacion);
            return;
        } catch (\Exception $e) {
            Log::error("Error generating dispatch order: " . $e->getMessage());
            return;
        }
    }

    public function storeNewDispatchOrder($pedido,  $estado, $fechaLiberacion)
    {
        $newDispatchOrder = new GpOrdenDespacho();
        $newDispatchOrder->pedido_id = $pedido->id;
        $newDispatchOrder->estado = $estado;
        $newDispatchOrder->fecha_hora_liberacion = $fechaLiberacion;
        $newDispatchOrder->PTO_VTA_ID = $pedido->PTO_VTA_ID;
        $newDispatchOrder->BOD_BODEGA = $pedido->BOD_BODEGA;
        $newDispatchOrder->save();

        $newDispatchOrder->orden = $this->generateDispatchOrderNumber($newDispatchOrder);
        $newDispatchOrder->save();
        return $newDispatchOrder;
    }

    public function shouldSkipOrderGeneration($pedido)
    {
        return in_array($pedido->estado, ['orden_de_compra', 'traslado']) || $pedido->despachado;
    }

    public function checkForExistingDispatchOrder(GpPedido $pedido)
    {
        return GpOrdenDespacho::where('pedido_id', $pedido->id)->exists();
    }

    public function determineDispatchStatusAndDate($pedido)
    {
        $estado = self::STATUS_PENDING;
        $fechaLiberacion = null;

        if ($pedido->COND_ID != 'CONTADO') {
            $fechaLiberacion = date('Y-m-d H:i:s');
            $estado = self::STATUS_RELEASED;
        }

        $formaPago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $pedido->COND_ID)->first();
        if ($formaPago?->DESC_FORMA_PAGO_TIPO_COBRO === 'CHEQUE') {
            $cliente = GpCliente::find($pedido->cliente_id);
            if ($cliente && $cliente->CLI_DIA_GRACIA_CHEQUE_A_FECHA == 0) {
                $estado = self::STATUS_PENDING;
            }
        }
        if ($pedido->factura_posterior_despacho && $pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
            $estado = self::STATUS_PENDING;
        }
        return [$estado, $fechaLiberacion];
    }

    public function generateDispatchOrderNumber($dipatchOrder)
    {
        return 'ODD' . str_pad($dipatchOrder->id, 8, '0', STR_PAD_LEFT);
    }

    public function sendInvoiceGp($pedido)
    {
        // verifica si tiene ya ha recibido todos los item L del pedido
        if ($this->purchaseOrderService->checkPurchaseOrderRecib($pedido)) {
            return;
        }

        // verifica si el pedido tiene un item de traslado pendiente, si tiene no se facturara
        if ($this->checkOrderHaveItemTransferPending($pedido)) {
            Log::warning("Pedido {$pedido->pedido} tiene traslado pendiente. No se facturará.");
            return;
        }

        // verifica si el pedido esta en estado 'orden_de_compra'
        if ($pedido->estado == 'orden_de_compra') {
            return;
        }
        // cuando el pedido esta en estado espera realiza otras validaciones adicionales
        if ($pedido->estado == 'espera') {
            Log::info("Pedido {$pedido->pedido} en estado 'espera', verificando si puede cambiar a 'enviando'...");
            if (!$this->orderReadyToShip($pedido)) {
                Log::warning("Pedido {$pedido->pedido} no está listo para facturación (estado: 'espera').");
                return;
            }
            $pedido->estado = 'enviando';
            $pedido->save();
        }

        // 🚫 Validación final antes de facturar

        if (!$this->checkFinalInvoiceValidation($pedido)) {
            Log::warning("Pedido {$pedido->pedido} no cumple condiciones para facturar. Estado actual: {$pedido->estado}");
            return;
        }

        $sucursalEnvio = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();
        $sucursalFacturacion = GpClienteSucursal::where('id', $pedido->sucursal_facturacion_id)->first();
        $docId = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)
            ->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'FAC%')->first();

        if (!$docId) {
            return;
        }
        $FAC_VTA_ID_DIRECCION_ENVIO = ($pedido->despacho == 'Retiro en bodega') ?
            $sucursalFacturacion->CLI_SUC_ID_DIRECCION : $sucursalEnvio->CLI_SUC_ID_DIRECCION;

        if (!$pedido->factura_origen) {
            $pedido->factura_origen = '';
        }

        $cabecera = $this->mapFormatHeaderInvoice($pedido, $sucursalFacturacion, $docId, $FAC_VTA_ID_DIRECCION_ENVIO);

        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        if ($detalles->isEmpty()) {
            Log::error("Pedido {$pedido->pedido} sin detalles");
            return;
        }
        $detallePedido = $this->mapFormatDetailInvoice($detalles, $pedido);

        $params = [
            'Cabecera' => $cabecera,
            'Detalle' => $detallePedido
        ];

        if ($pedido->rechazado) {
            $pedido->estado = 'rechazado';
            $pedido->save();
            Log::error("Pedido {$pedido->pedido} rechazado");
            return;
        }

        $result = $this->mswService->transaccional('MST_FAC_VTA', $params);
        if (isset($result['MST_FAC_VTAResult'])) {
            $pedido->MST_FAC_VTAResult = $result['MST_FAC_VTAResult'];
            $pedido->save();
            $this->respuestaFacturaVenta($pedido);
        }
    }


    public function checkOrderHaveItemTransferPending(GpPedido $order): bool
    {
        $tieneTraslado = $this->getDataCheckTraslado($order);
        if ($tieneTraslado) {
            $order->estado = 'traslado';
            $order->save();
            Log::info("Pedido {$order->pedido} actualizado a estado 'traslado' por traslado pendiente. No se Factura");
        }
        return $tieneTraslado;
    }


    public function orderReadyToShip(GpPedido $order): bool
    {
        // no debe estar en estado traslado o orden de compra
        if (in_array($order->estado, ['traslado', 'orden_de_compra'])) {
            return false;
        }
        // Validar aprobación comercial
        if ($order->requiere_aprobacion_comercial === 'yes' && $order->aprobado_comercial !== 'yes') {
            return false;
        }

        // Validar aprobación crediticia
        if ($order->requiere_aprobacion_crediticia === 'yes' && $order->aprobado_crediticio !== 'yes') {
            return false;
        }

        // Validar que no esté rechazado
        if ($order->rechazado) {
            return false;
        }

        // // verifica que no tenga factura posterior despacho y factura siguiente mes
        // if ($order->factura_a_demanda && !$order->factura_siguiente_mes) {
        //     return false;
        // }

        // // verifica que no tenga factura posterior despacho y factura siguiente mes
        // if (!$order->factura_a_demanda && $order->factura_siguiente_mes) {
        //     return false;
        // }

        // Validar que tenga detalles
        $tieneDetalles = GpPedidoDetalle::where('pedido_id', $order->id)
            ->exists();
        if (!$tieneDetalles) {
            return false;
        }
        return true;
    }


    public function mapFormatHeaderInvoice($pedido, $sucursalFacturacion, $docId, $FAC_VTA_ID_DIRECCION_ENVIO)
    {
        return [[
            'FAC_VTA_DOCU_ORIGEN' => $pedido->pedido,
            'FAC_VTA_DOC_COMPRA' => $pedido->orden_de_compra,
            'FAC_VTA_ID_TIPO' => 3,
            'FAC_VTA_PUNTO_VENTA' => $pedido->PTO_VTA_ID,
            'FAC_VTA_DOC_ID' => $docId->PTO_VTA_ID_FACTURA_NC,
            'FAC_VTA_ID_CLIENTE' => $pedido->CLI_RUC,
            'FAC_VTA_ID_VENDEDOR' => $pedido->VEND_ID,
            'FAC_VTA_ID_DIRECCION' => $sucursalFacturacion->CLI_SUC_ID_DIRECCION,
            'FAC_VTA_ID_DIRECCION_ENVIO' => $FAC_VTA_ID_DIRECCION_ENVIO,
            'FAC_VTA_COND_PAGO' => $pedido->COND_ID,
            'FAC_VTA_NOTA_DOCU' => $pedido->factura_origen,
            'FAC_VTA_DATE_DOCU' => date('Y-m-d H:i:s'),
            'FAC_VTA_FORMA_PAGO' => $pedido->DESC_FORMA_PAGO_CODIGO,
            'FAC_VTA_BODEGA' => $pedido->BOD_BODEGA,
            'FAC_VTA_SUBTOTAL' => number_format($pedido->subtotal - $pedido->descuento, 2, '.', ''),
            'FAC_VTA_DESCUENTO_COMERCIAL' => 0,
            'FAC_VTA_IMPUESTO' => $pedido->iva,
            'FAC_VTA_TOTAL' => number_format($pedido->subtotal - $pedido->descuento + $pedido->iva, 2, '.', ''), //$pedido->total,
            'FAC_VTA_TIPO_DESPACHO' => $pedido->despacho,
        ]];
    }

    public function mapFormatDetailInvoice($detalles, $pedido)
    {
        return $detalles->values()->map(function ($detalle, $index) use ($pedido) {
            return [
                'FAC_VTA_DET_LINEA'           => $index + 1,
                'FAC_VTA_DET_DOCU_ORIGEN'     => $pedido->pedido,
                'FAC_VTA_DET_ID_TIPO'         => 3,
                'FAC_VTA_DET_ARTICULO'        => $detalle->ART_CODIGO,
                'FAC_VTA_DET_DESC_ARTICULO'   => $detalle->ART_DESCRIPCION,
                'FAC_VTA_DET_UNIDAD_MEDIDA'   => $detalle->ART_PLAN_UMEDIDA,
                'FAC_VTA_DET_BODEGA'          => $pedido->BOD_BODEGA,
                'FAC_VTA_DET_CANTIDAD'        => $detalle->ART_CANTIDAD,
                'FAC_VTA_DET_COSTO_UNITARIO'  => $detalle->ART_VALOR,
                'FAC_VTA_DET_PRECIO_UNITARIO' => $detalle->ART_VALOR,
                'FAC_VTA_DET_DESCUENTO'       => $detalle->ART_DESCUENTO,
                'FAC_VTA_DET_IMPUESTO_IVA'    => $detalle->ART_IVA,
                'FAC_VTA_DET_TOTAL'           => number_format(
                    ($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->ART_CANTIDAD,
                    2,
                    '.',
                    ''
                ),
            ];
        })->toArray();
    }


    public function checkFinalInvoiceValidation(GpPedido $pedido): bool
    {
        if ($pedido->estado != 'enviando') {
            return false;
        }

        if ($pedido->requiere_aprobacion_crediticia === 'yes' && $pedido->aprobado_crediticio !== 'yes') {
            return false;
        }

        if ($pedido->requiere_aprobacion_comercial === 'yes' && $pedido->aprobado_comercial !== 'yes') {
            return false;
        }

        // // verifica que no tenga factura posterior despacho y factura siguiente mes
        // if ($pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
        //     return false;
        // }

        // // verifica que no tenga factura posterior despacho y factura siguiente mes
        // if (!$pedido->factura_a_demanda && $pedido->factura_siguiente_mes) {
        //     return false;
        // }

        return true;
    }



    public function respuestaFacturaVenta($order)
    {
        $pedido = GpPedido::where('pedido', $order->pedido)->first();
        $params = [
            'documento_origen' => $pedido->pedido,
            'tipo_documento' => 3,
        ];
        $result = $this->mswService->transaccional('MST_Respuesta_Factura_Vta', $params);
        $respuesta = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0] ?? null;

        if ($respuesta && !empty($respuesta['DOC_GENERADO'])) {
            $pedido->factura = $respuesta['DOC_GENERADO'];
            $pedido->FAC_VTA_MENSAJE = $respuesta['FAC_VTA_MENSAJE'] ?? null;
            $pedido->estado = 'facturado';
            $pedido->fecha_factura = now()->toDateString();
            $pedido->fecha_hora_factura = now();
        } else {
            $pedido->FAC_VTA_MENSAJE = $respuesta['FAC_VTA_MENSAJE'] ?? 'Error no definido por GP';
            $pedido->estado = 'espera';
        }

        try {
            $pedido->save();
        } catch (\Throwable $e) {
            logger()->error("Error guardando pedido en repuesta de factura {$pedido->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
