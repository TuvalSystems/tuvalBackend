<?php

namespace App\Services\Funciones;

use App\Enums\Ventas\OrderOrigenEnum;
use Carbon\Carbon;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Enums\Ventas\OrderStatus;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Log;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Models\Reservas\GpCotizacionReservaDetalleLog;
use App\Models\Reservas\GpReserva;
use App\Repositories\Pedidos\OrderRepository;
use App\Services\Api\Transacciones\ApiGpProcessInviceVta;
use App\Services\Builders\Bodega\Contracts\DispatchOrderParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\SalesTransferParamsBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;

class InvoiceService implements InvoiceServiceInterface
{
    use JsonResponseTrait;
    const STATUS_PENDING = 'pendiente';
    const STATUS_RELEASED = 'liberada';

    // Implementa todos los metodos de facturacion de pedidos, devoluciones, aprobaciones, etc

    public function __construct(
        protected readonly PurchaseOrderServiceInterface $purchaseOrderService,
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly ApiGpProcessInviceVta $apiGpProcessInvoice,
        protected readonly OrderRepository $orderRepository,
        protected readonly SalesTransferParamsBuilderInterface $salesTransfer,
        protected readonly DispatchOrderParamsBuilderInterface $dispatchBuilder
    ) {}

    /** Funcion de checkenviarfactura pasada aqui para reutilizar en otros servicios
     *  y que no se me cuelguen los metodos
     */

    public function checkSendInvoice(GpPedido $pedido, $trasladado = false, $checkDemanda = true)
    {
        if (
            $this->shouldSkipProcessing($pedido) ||
            $this->shouldSkipDueToTransferStatus($pedido, $trasladado) ||
            $this->processPurchaseOrders($pedido) || ($checkDemanda && $this->checkInvoiceOnDemand($pedido))
        ) {
            return;
        }


        $this->generateDisptachOrder($pedido);
        $this->functionService->recalculateOrder($pedido->id);
        $this->sendInvoiceGp($pedido);
    }

    /** funciones anidadas a  checkSendInvoice*/
    protected function shouldSkipProcessing(GpPedido $order)
    {
        return $this->shouldSkipOrderForStatus($order) ||
            ($order->requiere_aprobacion_comercial == 'yes' && $order->aprobado_comercial == 'no') ||
            ($order->requiere_aprobacion_crediticia == 'yes' && $order->aprobado_crediticio == 'no');
    }

    protected function shouldSkipOrderForStatus(GpPedido $pedido): bool
    {
        return \in_array($pedido->estado, [
            OrderStatus::ORDEN_DE_COMPRA,
            OrderStatus::TRASLADO,
            OrderStatus::RECHAZADO,
            OrderStatus::ANULADA,
            OrderStatus::ELIMINADO
        ], true);
    }
    protected function shouldSkipDueToTransferStatus(GpPedido $pedido, $trasladado): bool
    {
        return $trasladado
            ? $this->checkTrasladado($pedido)
            : $this->checkTraslado($pedido);
    }



    public function checkTrasladado(GpPedido $pedido): bool
    {
        if ($pedido->estado === OrderStatus::FACTURADO) {
            return false;
        }
        $tieneTraslado = $this->orderRepository->getDataCheckoutTraslate($pedido->id, $pedido->BOD_BODEGA);
        $nuevoEstado = $tieneTraslado ? OrderStatus::TRASLADO : OrderStatus::ESPERA;
        if ($pedido->estado !== $nuevoEstado) {
            $pedido->estado = $nuevoEstado;
            $pedido->save();
        }
        return $tieneTraslado;
    }


    public function checkTraslado(GpPedido $pedido): bool
    {
        if ($pedido->estado === OrderStatus::FACTURADO) {
            return false;
        }
        $hayTraslado    = $this->orderRepository->getDataCheckoutTraslate($pedido->id, $pedido->BOD_BODEGA);
        $detalles       = $this->orderRepository->getDetailForTraslate($pedido->id, $pedido->BOD_BODEGA);
        $nuevoEstado    = $hayTraslado ? OrderStatus::TRASLADO : OrderStatus::ESPERA;
        if ($nuevoEstado === OrderStatus::TRASLADO && $detalles->isNotEmpty()) {
            $this->saveTransferenciasVentas($pedido, $detalles);
        }

        if ($pedido->estado !== $nuevoEstado) {
            $pedido->estado = $nuevoEstado;
            $pedido->save();
        }

        return $hayTraslado;
    }



    protected function processPurchaseOrders(GpPedido $pedido)
    {
        $this->purchaseOrderService->checkPurchaseOrder($pedido);
        $this->purchaseOrderService->sendPurchaseOrders();
        $this->purchaseOrderService->checkPurchaseOrderRecib($pedido);
        $pedido->refresh();
        return $this->shouldSkipOrderForStatus($pedido);
    }


    /**
     *  remplaza a la funcion savetransferenciasventas que esta en TrasnferenciasController
     */

    protected function saveTransferenciasVentas(GpPedido $pedido, $detalles)
    {
        foreach ($detalles as $detalle) {
            $trfvta = $this->salesTransfer->build($pedido, $detalle);
            $trfvta->save();
            $detalle->traslado = 1;
            $detalle->save();
        }
    }


    protected function checkInvoiceOnDemand(GpPedido $pedido): bool
    {
        $pedido->loadMissing('gpcliente');
        $cliente = $pedido->gpcliente;
        if (!$cliente) {
            throw new \Exception("El pedido #{$pedido->pedido} no tiene un cliente asociado válido.");
        }
        $this->setInvoiceFlags($pedido, $cliente);
        $pedido->save();
        return $this->processInvoiceDecision($pedido);
    }

    protected function setInvoiceFlags(GpPedido $pedido, GpCliente $cliente)
    {
        $pedido->factura_posterior_despacho = 0;
        $pedido->factura_a_demanda          = 0;
        $pedido->factura_siguiente_mes      = 0;

        if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO === 'SI') {
            $pedido->factura_posterior_despacho = 1;

            $hoy     = (int) date('d');
            $diaMax  = (int) $cliente->CLI_DIA_MAXIMO_MES_FACTURACION;

            if ($diaMax == 1) {
                $pedido->factura_a_demanda = 1;
            } elseif ($diaMax > 1 && $hoy > $diaMax) {
                $pedido->factura_siguiente_mes = 1;
            }
        }
    }

    protected function processInvoiceDecision(GpPedido $pedido): bool
    {
        if (!$pedido->factura_posterior_despacho) {
            return false;
        }
        if ($pedido->factura_a_demanda || $pedido->factura_siguiente_mes) {
            $this->generateDisptachOrder($pedido);
            return true;
        }

        return false;
    }

    public function generateDisptachOrder(GpPedido $pedido)
    {
        if (
            $this->checkTrasladado($pedido) ||
            $this->shouldSkipOrderGeneration($pedido) ||
            $this->checkForExistingDispatchOrder($pedido)
        ) {
            return;
        }
        [$estado, $fechaLiberacion] = $this->determineDispatchStatusAndDate($pedido);
        $ordenDespacho =  $this->dispatchBuilder->build($pedido, $estado, $fechaLiberacion);
        $ordenDespacho->save();
    }


    protected function shouldSkipOrderGeneration(GpPedido $pedido)
    {
        return $this->shouldSkipOrderForStatus($pedido) || $pedido->despachado;
    }

    protected function checkForExistingDispatchOrder(GpPedido $pedido)
    {
        return GpOrdenDespacho::where('pedido_id', $pedido->id)->exists();
    }

    protected function determineDispatchStatusAndDate(GpPedido $pedido)
    {
        $estado             = self::STATUS_PENDING;
        $fechaLiberacion    = null;
        $esContado          = $pedido->COND_ID == 'CONTADO';
        $bloqueoFactura     = $pedido->factura_posterior_despacho && $pedido->factura_a_demanda && !$pedido->factura_siguiente_mes;

        // 3. Solo si NO es contado y NO hay bloqueo de factura, evaluamos liberarlo
        if (!$esContado && !$bloqueoFactura) {
            // Asumimos que se libera
            $estado = self::STATUS_RELEASED;
            $fechaLiberacion = now()->format('Y-m-d H:i:s');

            // Única excepción: consultamos la BD para ver si es Cheque sin días de gracia
            $formaPago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CONDICION_PAGO', $pedido->COND_ID)->first();

            if ($formaPago?->DESC_FORMA_PAGO_TIPO_COBRO === 'CHEQUE') {
                $cliente = GpCliente::find($pedido->cliente_id);

                if ($cliente && $cliente->CLI_DIA_GRACIA_CHEQUE_A_FECHA == 0) {
                    // Revertimos a pendiente
                    $estado = self::STATUS_PENDING;
                    $fechaLiberacion = null;
                }
            }
        }

        // 4. Un solo punto de salida. ¡SonarQube feliz!
        return [$estado, $fechaLiberacion];
    }


    public function sendInvoiceGp(GpPedido $pedido)
    {
        if ($pedido->rechazado || $this->shouldSkipOrderForStatus($pedido)) {
            if ($pedido->rechazado && $pedido->estado !== OrderStatus::RECHAZADO) {
                $pedido->estado = OrderStatus::RECHAZADO;
                $pedido->save();
            }
            return;
        }
        // verifica si tiene ya ha recibido todos los item L del pedido
        if (
            $this->purchaseOrderService->checkPurchaseOrderRecib($pedido) ||
            $this->checkOrderHaveItemTransferPending($pedido) ||
            $this->shouldSkipOrderForStatus($pedido)
        ) {
            Log::warning("Pedido {$pedido->pedido} pausado por dependencias logísticas (OC/Traslado/Estado).");
            return;
        }

        // cuando el pedido esta en estado espera realiza otras validaciones adicionales
        if ($pedido->estado === OrderStatus::ESPERA) {
            if (!$this->orderReadyToShip($pedido)) {
                Log::warning("Pedido {$pedido->pedido} no está listo para facturación (estado: 'espera o traslado o orden_de_compra').");
                return;
            }

            // validacion si pedido nace de reserva o cotizacion
            if (!$this->isInvoiceDateValidForReservedOrder($pedido)) {
                Log::warning(
                    'Pedido no cumple validación de origen/fecha',
                    [
                        'pedido_id'     => $pedido->id,
                        'numero'        => $pedido->pedido ?? null,
                        'origen'        => $pedido->origen,
                        'fe_fact_res'   => $pedido->fe_facturacion_reserva,
                        'today'         => now()->toDateString(),
                        'estado_prev'   => $pedido->estado->value,
                    ]
                );

                if ($pedido->estado !== OrderStatus::ESPERA) {
                    $pedido->estado = OrderStatus::ESPERA;
                    $pedido->save();
                }
                return;
            }
            Log::info("Pedido {$pedido->pedido} cumple condiciones para cambiar a 'enviando'.");
            $pedido->estado = OrderStatus::ENVIANDO;
            $pedido->save();
        }

        $pedido->refresh();

        // 🚫 Validación final antes de facturar
        if (!$this->checkFinalInvoiceValidation($pedido)) {
            Log::info("Pedido {$pedido->pedido} no cumple condiciones para facturar. Estado actual: {$pedido->estado->value}");
            return;
        }


        $sucursales = GpClienteSucursal::whereIn('id', [
            $pedido->sucursal_id,
            $pedido->sucursal_facturacion_id
        ])->get()->keyBy('id');

        $sucursalEnvio          = $sucursales->get($pedido->sucursal_id);
        $sucursalFacturacion    = $sucursales->get($pedido->sucursal_facturacion_id);

        if (!$sucursalEnvio || !$sucursalFacturacion) {
            Log::error("Pedido {$pedido->pedido}: Falta información de sucursal de envío o facturación.");
            throw new \Exception("Datos de sucursal incompletos.");
        }
        $docId      = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'FAC%')->first();
        if (!$docId) {
            Log::warning("Pedido {$pedido->pedido} no tiene docId.");
            return;
        }
        $factVtaDireccionEnvio = ($pedido->despacho == 'Retiro en bodega') ?
            $sucursalFacturacion->CLI_SUC_ID_DIRECCION : $sucursalEnvio->CLI_SUC_ID_DIRECCION;

        $pedido->factura_origen = $pedido->factura_origen ?? '';

        // verificar si el pedido ya fue facturado
        $existInvoice = $this->apiGpProcessInvoice->responseDocumentInvoiceVta($this->formatParamsInvoiceValidate($pedido));
        if ($existInvoice['success'] === true) {
            $info               = data_get($existInvoice, 'data', []);
            $pedido             = GpPedido::where('pedido', $pedido->pedido)->first();
            $pedido->factura    = data_get($info, 'numeroDocGenerado', null);
            $pedido->estado     = OrderStatus::FACTURADO;
            $pedido->save();
            return $pedido;
        }
        // eliminar factura existente si existe y no se proceso correctamente
        $deleteInvoice = $this->apiGpProcessInvoice->deleteDocumentInvoiceVta($this->formatParamsInvoiceValidate($pedido));
        if ($deleteInvoice['success'] === false) {
            throw new \Exception("No se pudo eliminar la factura existente para el pedido {$pedido->pedido}. Error: {$deleteInvoice['message']}");
        }

        $detalles       = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        if ($detalles->isEmpty()) {
            Log::error("Pedido {$pedido->pedido} sin detalles");
            throw new \Exception("El pedido {$pedido->pedido} no tiene detalles para facturar.");
        }
        $cabecera       = $this->mapFormatHeaderInvoice($pedido, $sucursalFacturacion, $docId, $factVtaDireccionEnvio);
        $detallePedido  = $this->mapFormatDetailInvoice($detalles, $pedido);

        $params = [
            'cabecera' => $cabecera,
            'detalle' => $detallePedido
        ];

        $result = $this->apiGpProcessInvoice->storeDocumentInvoiceVta($params);
        if ($result['success'] === false) {
            throw new \Exception("Error al facturar el pedido {$pedido->pedido}: {$result['message']}");
        }

        $pedido->MST_FAC_VTAResult = $result['message'];
        $pedido->save();
        $this->respuestaFacturaVenta($pedido);
    }


    protected function checkOrderHaveItemTransferPending(GpPedido $order): bool
    {
        $tieneTraslado = $this->orderRepository->getDataCheckoutTraslate($order->id, $order->BOD_BODEGA);
        if ($tieneTraslado) {
            $order->estado = OrderStatus::TRASLADO;
            $order->save();
            return true;
        }
        if (!in_array($order->estado, [OrderStatus::ENVIANDO, OrderStatus::FACTURADO])) {
            $order->estado = OrderStatus::ESPERA;
            $order->save();
        }

        return false;
    }


    public function orderReadyToShip(GpPedido $order): bool
    {
        $hasInvalidStatus   = $this->shouldSkipOrderForStatus($order);
        $missingCommercial  = $order->requiere_aprobacion_comercial === 'yes' && $order->aprobado_comercial !== 'yes';
        $missingCredit      = $order->requiere_aprobacion_crediticia === 'yes' && $order->aprobado_crediticio !== 'yes';
        $isRejected         = (bool) $order->rechazado;
        // no debe estar en estado traslado o orden de compra
        if ($hasInvalidStatus || $missingCommercial || $missingCredit || $isRejected) {
            return false;
        }
        // Validar que tenga detalles
        return GpPedidoDetalle::where('pedido_id', $order->id)
            ->exists();
    }


    protected function isInvoiceDateValidForReservedOrder(GpPedido $pedido): bool
    {
        if ($pedido->origen !== OrderOrigenEnum::FACTURA_DEMANDA->value) {
            return true;
        }

        if (empty($pedido->fe_facturacion_reserva)) {
            return false;
        }

        $fechaReserva = Carbon::parse($pedido->fe_facturacion_reserva)->startOfDay();
        $hoy = now()->startOfDay();

        return $fechaReserva->lte($hoy);
    }


    protected function mapFormatHeaderInvoice($pedido, $sucursalFacturacion, $docId, $direccionEnvio)
    {
        return [
            'FAC_VTA_DOCU_ORIGEN'           => $pedido->pedido,
            'FAC_VTA_DOC_COMPRA'            => $pedido->orden_de_compra,
            'FAC_VTA_ID_TIPO'               => 3,
            'FAC_VTA_PUNTO_VENTA'           => $pedido->PTO_VTA_ID,
            'FAC_VTA_DOC_ID'                => $docId->PTO_VTA_ID_FACTURA_NC,
            'FAC_VTA_ID_CLIENTE'            => $pedido->CLI_RUC,
            'FAC_VTA_ID_VENDEDOR'           => $pedido->VEND_ID,
            'FAC_VTA_ID_DIRECCION'          => $sucursalFacturacion->CLI_SUC_ID_DIRECCION,
            'FAC_VTA_ID_DIRECCION_ENVIO'    => $direccionEnvio,
            'FAC_VTA_COND_PAGO'             => $pedido->COND_ID,
            'FAC_VTA_NOTA_DOCU'             => $pedido->factura_origen,
            'FAC_VTA_DATE_DOCU'             => now()->toDateTimeString(),
            'FAC_VTA_FORMA_PAGO'            => $pedido->DESC_FORMA_PAGO_CODIGO,
            'FAC_VTA_BODEGA'                => $pedido->BOD_BODEGA,
            'FAC_VTA_SUBTOTAL'              => number_format($pedido->subtotal - $pedido->descuento, 2, '.', ''),
            'FAC_VTA_DESCUENTO_COMERCIAL'   => 0,
            'FAC_VTA_IMPUESTO'              => $pedido->iva,
            'FAC_VTA_TOTAL'                 => number_format($pedido->subtotal - $pedido->descuento + $pedido->iva, 2, '.', ''), //$pedido->total,
            'FAC_VTA_TIPO_DESPACHO'         => $pedido->despacho,
            'FAC_VTA_NOTA_CLIENTE'          => $this->normalizeTextNoteClient($pedido->nota_cliente),

            //campos aidicionales para el envio a sp de creacion de facturas
            'userIdCreator'                => $pedido->USERID,
            'condIdOriginal'               => $pedido->COND_ID_ORIGINAL,
        ];
    }

    protected function normalizeTextNoteClient(?string $nota): string
    {
        if ($nota === null) {
            return '';
        }
        $clean = preg_replace('/\R+/u', ' ', $nota);
        return trim(preg_replace('/[^\S\r\n]+/u', ' ', $clean));
    }

    protected function mapFormatDetailInvoice($detalles, $pedido)
    {
        return $detalles->values()->map(function ($detalle, $index) use ($pedido) {
            return [
                'FAC_VTA_DET_LINEA'             => $index + 1,
                'FAC_VTA_DET_DOCU_ORIGEN'       => $pedido->pedido,
                'FAC_VTA_DET_ID_TIPO'           => 3,
                'FAC_VTA_DET_ARTICULO'          => $detalle->ART_CODIGO,
                'FAC_VTA_DET_DESC_ARTICULO'     => $detalle->ART_DESCRIPCION,
                'FAC_VTA_DET_UNIDAD_MEDIDA'     => $detalle->ART_PLAN_UMEDIDA,
                'FAC_VTA_DET_BODEGA'            => $pedido->BOD_BODEGA,
                'FAC_VTA_DET_CANTIDAD'          => $detalle->ART_CANTIDAD,
                'FAC_VTA_DET_COSTO_UNITARIO'    => $detalle->ART_VALOR,
                'FAC_VTA_DET_PRECIO_UNITARIO'   => $detalle->ART_VALOR,
                'FAC_VTA_DET_DESCUENTO'         => $detalle->ART_DESCUENTO,
                'FAC_VTA_DET_IMPUESTO_IVA'      => $detalle->ART_IVA,
                'FAC_VTA_DET_TOTAL'             => number_format(($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->ART_CANTIDAD, 2, '.', ''),

                //campos aidicionales para el envio a sp de creacion de facturas
                'descripcionOrginal'            => $detalle->ART_DESCRIPCION_ORIGINAL,
                'valorOrginal'                  => $detalle->ART_VALOR_ORIGINAL,
                'descuentoOrginal'              => $detalle->descuento_original,
            ];
        })->toArray();
    }

    protected function formatParamsInvoiceValidate($pedido)
    {
        return [
            'documentoOrigen' => $pedido->pedido,
            'tipoDocumento' => 3
        ];
    }



    protected function checkFinalInvoiceValidation(GpPedido $pedido): bool
    {
        $isInvalidStatus    = $pedido->estado !== OrderStatus::ENVIANDO;
        $hasInvoice         = $pedido->factura !== null && trim((string)$pedido->factura) !== '';
        $missingCommercial  = $pedido->requiere_aprobacion_comercial === 'yes' && $pedido->aprobado_comercial === 'no';
        $missingCredit      = $pedido->requiere_aprobacion_crediticia === 'yes' && $pedido->aprobado_crediticio === 'no';

        if ($isInvalidStatus || $hasInvoice || $missingCommercial || $missingCredit) {
            Log::warning("Validación fallida facturación pedido {$pedido->pedido}", [
                'status'            => $pedido->estado->value,
                'invoice'           => $hasInvoice,
                'invoice_value'     => $pedido->factura,
                'comm_approval'     => $missingCommercial,
                'credit_approval'   => $missingCredit
            ]);
            return false;
        }

        return true;
    }

    public function respuestaFacturaVenta(GpPedido $order)
    {
        $pedido = GpPedido::find($order->id);
        $existInvoice = $this->apiGpProcessInvoice->responseDocumentInvoiceVta($this->formatParamsInvoiceValidate($pedido));
        if ($existInvoice['success'] === false) {
            $pedido->FAC_VTA_MENSAJE = $existInvoice['message'] ?? 'Error no definido por GP';
            $pedido->estado = OrderStatus::ESPERA;
            throw new \Exception("Ocurrio un error al obtener la factura: {$existInvoice['message']}");
        }
        $info                       = data_get($existInvoice, 'data', []);
        $pedido->factura            = data_get($info, 'numeroDocGenerado', null);
        $pedido->FAC_VTA_MENSAJE    = data_get($existInvoice, 'message', null);
        $pedido->estado             = OrderStatus::FACTURADO;
        $pedido->fecha_factura      = now()->toDateString();
        $pedido->fecha_hora_factura = now();
        Log::info("Pedido {$pedido->pedido} facturado. numero de factura: {$pedido->factura}. Estado del pedido {$pedido->estado->value}");
        $pedido->save();
        try {
            $this->cleanStockReservedQuotation($pedido->cotizacion_id, $pedido->id, $pedido->BOD_BODEGA);
        } catch (\Exception $e) {
            Log::error("Factura creada {$pedido->factura} pero falló limpieza de stock: " . $e->getMessage());
            // No lanzamos la excepción para que el estado FACTURADO se mantenga
        }
    }

    /** proceso de limpieza de stock de cotizacion de reserva  */
    protected function cleanStockReservedQuotation($quoteId, $orderId, $warehouse)
    {
        $order = GpPedido::find($orderId);
        if (!$order || $order->estado == OrderStatus::ESPERA) {
            return;
        }
        $cotResLog = GpCotizacionReservaLog::where('cotizacion_destino_id', $quoteId)->first();
        if (!$cotResLog || empty($cotResLog->cotizacion_origen_id)) {
            return;
        }

        $reservedId     = data_get($cotResLog, 'reserva_id', null);
        $quoteOriginId  = data_get($cotResLog, 'cotizacion_origen_id', null);
        if (!$quoteOriginId) {
            return;
        }
        $order->load('detallepedido.pedidodetallestocks');
        // 1) Consumo del PEDIDO por (ART_CODIGO|BODEGA)
        $pedidoPorClave = []; // clave = ART_CODIGO|BODEGA
        foreach ($order->detallepedido as $det) {
            foreach ($det->pedidodetallestocks as $stk) {
                $clave = $det->ART_CODIGO . '|' . $stk->STO_ART_BODEGA;
                $pedidoPorClave[$clave] = ($pedidoPorClave[$clave] ?? 0) + (int) $stk->seleccionado;
            }
        }

        if (empty($pedidoPorClave)) {
            return; // nada que descontar
        }
        // 2) Stock IDs registrados en el LOG (pivote real)
        $stockIds = GpCotizacionReservaDetalleLog::where('ct_reserva_id', $cotResLog->id)
            ->pluck('stock_id')
            ->filter()
            ->unique()
            ->values();

        if ($stockIds->isEmpty()) {
            return;
        }

        $quoteStockRows = GpCotizacionDetalleStock::query()
            ->from('gp_cotizacion_detalle_stocks as s')
            ->join('gp_cotizacion_detalles as d', 'd.id', '=', 's.detalle_id')
            ->select(
                's.id',
                'd.ART_CODIGO',
                's.STO_ART_BODEGA',
                's.seleccionado',
                's.cantidad_facturada',
                's.cantidad_cancelada',
                's.cantidad_reservada'
            )
            ->whereIn('s.id', $stockIds->all())
            ->get();

        if ($quoteStockRows->isEmpty()) {
            return;
        }

        $cotizacionPorClave     = $quoteStockRows->keyBy(fn($row) => $row->ART_CODIGO . '|' . $row->STO_ART_BODEGA);
        $clavesAfectadas        = array_values(array_intersect(array_keys($pedidoPorClave), $cotizacionPorClave->keys()->all()));
        if (empty($clavesAfectadas)) {
            return;
        }

        $idsParaLock = array_map(fn($k) => $cotizacionPorClave[$k]->id, $clavesAfectadas);
        GpCotizacionDetalleStock::whereIn('id', $idsParaLock)->get();
        foreach ($clavesAfectadas as $k) {
            $row            = $cotizacionPorClave[$k];
            $pedidoQty      = (int) $pedidoPorClave[$k];
            $selActual      = (int) $row->seleccionado;
            $facturado      = (int) ($row->cantidad_facturada ?? 0);
            $cancelado      = (int) ($row->cantidad_cancelada ?? 0);
            $authorizado    = (int) ($row->cantidad_reservada ?? 0);
            $limite         = max(0, $authorizado - $cancelado);
            $remanenteFact  = max(0, $limite - $facturado);
            $incFacturable  = min($pedidoQty, $remanenteFact);
            $consumoReserva = min($incFacturable, $selActual);
            $nuevoSelect    = $selActual - $consumoReserva;
            $nuevoFacturado = $facturado + $incFacturable;

            if ($nuevoSelect !== $selActual) {
                $row->seleccionado          = $nuevoSelect;
                $row->cantidad_facturada    = $nuevoFacturado;
                $row->save();
            }
        }
        $this->updateCheckStatusReservation($reservedId);
    }

    protected function updateCheckStatusReservation(int $reserveId)
    {
        $reserva = GpReserva::find($reserveId);
        if (!$reserva) {
            return;
        }
        $tienePendientes = GpCotizacionDetalleStock::where('cotizacion_id', $reserva->cotizacion_id)
            ->whereRaw('(COALESCE(cantidad_reservada, 0) - COALESCE(cantidad_facturada, 0) - COALESCE(cantidad_cancelada, 0)) > 0')
            ->exists();

        if (!$tienePendientes) {
            // Ajusta el nombre de la columna/valor según tu modelo (estado, estado_reserva, etc.)
            $reserva->estado = 'procesado';
            $reserva->save();

            GpCotizacion::where('id', $reserva->cotizacion_id) // O la columna ID real
                ->update(['estado' => 'procesado']);
        }
    }
}
