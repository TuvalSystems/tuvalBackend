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

        $this->checkTransferStatus($pedido, $trasladado);

        if ($this->processPurchaseOrders($pedido)) {
            return;
        }

        if ($checkDemanda && $this->checkInvoiceOnDemand($pedido)) {
            return;
        }

        $this->generateDisptachOrder($pedido);
        $this->functionService->recalculateOrder($pedido);
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

    public function checkTransferStatus(GpPedido $pedido, $trasladado)
    {
        if ($trasladado) {
            $this->checkTrasladado($pedido);
        } else {
            $this->checkTraslado($pedido);
        }
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
        $tieneTraslado = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA', '!=', $pedido->BOD_BODEGA)->exists();

        $pedido->estado = $tieneTraslado ? 'traslado' : 'espera';
        $pedido->save();
    }

    public function checkTraslado(GpPedido $pedido)
    {
        $hayTraslado = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            ->whereNull('traslado')
            ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA', '!=', $pedido->BOD_BODEGA)
            ->exists();
        if ($hayTraslado) {
            $pedido->estado = 'traslado';
            $pedido->save();

            // solo traemos los detalles que necesitamos
            $detalles = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
                ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
                ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
                ->whereNull('traslado')
                ->select([
                    'gp_pedido_detalles.*',
                    'gp_pedido_detalle_stocks.STO_ART_BODEGA',
                    'gp_pedido_detalle_stocks.seleccionado',
                    'gp_pedido_detalle_stocks.id AS detalle_stocks_id'
                ])->get();
            $this->saveTransferenciasVentas($pedido, $detalles);
        } else {
            $pedido->estado = 'espera';
            $pedido->save();
        }
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

            if ($diaMax === 1) {
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

        //caso factura a demanda
        if ($pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
            $this->generateDisptachOrder($pedido);
            return true;
        }

        //caso factura al siguiente mes
        if ($pedido->factura_siguiente_mes) {
            $this->generateDisptachOrder($pedido);
            return true;
        }

        return false;
    }

    public function generateDisptachOrder($pedido)
    {
        try {

            $this->checkTrasladado($pedido);
            if ($this->shouldSkipOrderGeneration($pedido)) {
                return false;
            }

            if ($this->checkForExistingDispatchOrder($pedido)) {
                return false;
            }

            [$estado, $fechaLiberacion] = $this->determineDispatchStatusAndDate($pedido);
            $orderNumber = $this->generateDispatchOrderNumber($pedido);

            GpOrdenDespacho::create([
                'orden' => $orderNumber,
                'pedido_id' => $pedido->id,
                'estado' => $estado,
                'fecha_hora_liberacion' => $fechaLiberacion,
                'PTO_VTA_ID' => $pedido->PTO_VTA_ID,
                'BOD_BODEGA' => $pedido->BOD_BODEGA
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Error generating dispatch order: " . $e->getMessage());
            return false;
        }

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

    public function generateDispatchOrderNumber($pedido)
    {
        $lastId = GpOrdenDespacho::max('id') ?? 0;
        return 'ODD' . str_pad($lastId + 1, 8, '0', STR_PAD_LEFT);
    }

    public function sendInvoiceGp($pedido)
    {
        $recib = $this->purchaseOrderService->checkPurchaseOrderRecib($pedido);
        if ($recib) {
            return;
        }
        $this->checkTrasladado($pedido);
        if ($pedido->estado == 'orden_de_compra') {
            return;
        }

        if ($pedido->estado == 'espera') {
            $pedido->estado = 'enviando';
            $pedido->save();
        }
        $sucursalEnvio = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();
        $sucursalFacturacion = GpClienteSucursal::where('id', $pedido->sucursal_facturacion_id)->first();
        $docId = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)
            ->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'FAC%')->first();

        if (!$docId) {
            return;
        }

        $FAC_VTA_ID_DIRECCION_ENVIO = $sucursalEnvio->CLI_SUC_ID_DIRECCION;
        if ($pedido->despacho == 'Retiro en bodega') {
            $FAC_VTA_ID_DIRECCION_ENVIO = $sucursalFacturacion->CLI_SUC_ID_DIRECCION;
        }

        if (!$pedido->factura_origen) {
            $pedido->factura_origen = '';
        }

        $cabecera = array(
            array(
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
            )
        );

        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        if ($detalles->isEmpty()) {
            return;
        }
        $detallePedido = [];
        $linea = 0;
        foreach ($detalles as $key => $detalle) {
            $linea++;
            $detallePedido[] = array(
                'FAC_VTA_DET_LINEA' => $linea,
                'FAC_VTA_DET_DOCU_ORIGEN' => $pedido->pedido,
                'FAC_VTA_DET_ID_TIPO' => 3,
                'FAC_VTA_DET_ARTICULO' => $detalle->ART_CODIGO,
                'FAC_VTA_DET_DESC_ARTICULO' => $detalle->ART_DESCRIPCION,
                'FAC_VTA_DET_UNIDAD_MEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                'FAC_VTA_DET_BODEGA' => $pedido->BOD_BODEGA,
                'FAC_VTA_DET_CANTIDAD' => $detalle->ART_CANTIDAD,
                'FAC_VTA_DET_COSTO_UNITARIO' => $detalle->ART_VALOR,
                'FAC_VTA_DET_PRECIO_UNITARIO' => $detalle->ART_VALOR,
                'FAC_VTA_DET_DESCUENTO' => $detalle->ART_DESCUENTO,
                'FAC_VTA_DET_IMPUESTO_IVA' => $detalle->ART_IVA,
                'FAC_VTA_DET_TOTAL' => number_format(($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->ART_CANTIDAD, 2, '.', ''),
            );
        }

        $params = [
            'Cabecera' => $cabecera,
            'Detalle' => $detallePedido
        ];

        if ($pedido->rechazado) {
            $pedido->estado = 'rechazado';
            $pedido->save();
            throw new \Exception("Pedido {$pedido->pedido} rechazado", 422);

        }

        if ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no') {
            return;
        }

        $result = $this->mswService->transaccional('MST_FAC_VTA', $params);
        if (isset($result['MST_FAC_VTAResult'])) {
            $pedido->MST_FAC_VTAResult = $result['MST_FAC_VTAResult'];
            $pedido->save();
            $this->respuestaFacturaVenta($pedido);
        }
    }

    public function respuestaFacturaVenta($pedido)
    {
        $params = [
            'documento_origen' => $pedido->pedido,
            'tipo_documento' => 3,
        ];
        $result = $this->mswService->transaccional('MST_Respuesta_Factura_Vta', $params);
        if (
            isset($result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'])
            && $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'] != ''
        ) {
            $pedido->factura = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'];
            $pedido->FAC_VTA_MENSAJE = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'];
            $pedido->estado = 'facturado';
            $pedido->fecha_factura = date('Y-m-d');
            $pedido->fecha_hora_factura = date('Y-m-d H:i:s');
            $pedido->save();
        } else {
            if (
                isset($result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'])
                && $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'] != ''
            )
                $pedido->FAC_VTA_MENSAJE = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'];
            else
                $pedido->FAC_VTA_MENSAJE = 'Error no definido por GP';
            $pedido->estado = 'espera';
            $pedido->save();
        }
    }
}
