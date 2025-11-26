<?php

namespace App\Services\Pedidos;

use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpArticulo;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Carbon\Carbon;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpVendedor;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalleStock;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\reservas\GpCotizacionReservaDetalleLog;
use App\Models\reservas\GpReserva;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\File;
use App\Services\Api\ApiSalesService;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService implements OrderServiceInterface
{
    use JsonResponseTrait;
    protected ApiSalesService $apiSalesService;
    protected ApiGpService $apiGpService;
    protected ApprovalServiceInterface $approvalService;

    protected OrderRepositoryInterface $orderRepository;
    protected ApiBillingService $apiBillingService;
    protected FunctionsServiceInterface $functionsService;
    protected PurchaseReceiptServiceInterface $purchaseReceiptService;
    protected $invoiceService;
    protected $orderAndQuotationService;
    protected $stockWarehouseService;
    protected $orderBuilder;

    public function __construct(
        ApiSalesService $apiSalesService,
        ApiGpService $apiGpService,
        ApprovalServiceInterface $approvalService,
        OrderRepositoryInterface $orderRepository,
        ApiBillingService $apiBillingService,
        FunctionsServiceInterface $functionsService,
        PurchaseReceiptServiceInterface $purchaseReceiptService,
        InvoiceServiceInterface $invoiceService,
        OrderAndQuotationServiceInterface $orderAndQuotationService,
        StockWarehouseServiceInterface $stockWarehouseService,
        OrderBuilderInterface $orderBuilderInterface
    ) {
        $this->apiGpService = $apiGpService;
        $this->apiSalesService = $apiSalesService;
        $this->approvalService = $approvalService;
        $this->orderRepository = $orderRepository;
        $this->apiBillingService = $apiBillingService;
        $this->functionsService = $functionsService;
        $this->purchaseReceiptService = $purchaseReceiptService;
        $this->invoiceService = $invoiceService;
        $this->orderAndQuotationService = $orderAndQuotationService;
        $this->stockWarehouseService = $stockWarehouseService;
        $this->orderBuilder = $orderBuilderInterface;
    }

    //me obtiene la informacion del documento xml_notificaciones



    public function getOrderInfo($id)
    {

        $pedido = $this->getOrderInfoById($id);

        if (!$pedido || $this->isPedidoBlocked($pedido)) {
            return 'No permitido';
        }

        $detalleData = $this->buildOrderDetail($pedido);
        $pedido['detalle'] = $detalleData['detalle'];
        $pedido['peso'] = $detalleData['peso'];
        $pedido['tipoimpuesto'] = $detalleData['tipoImpuesto'];
        $pedido['sumaimpuesto'] = $detalleData['sumaImpuesto'];
        $this->appendPagoAndClienteInfo($pedido);
        return $pedido;
    }

    public function getOrderInfoById($id)
    {
        return GpPedido::leftJoin('gp_cotizacions', 'gp_cotizacions.id', 'gp_pedidos.cotizacion_id')
            ->join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion', 'gp_usuarios.USERNAME')
            ->where('gp_pedidos.id', $id)
            ->first()
                ?->toArray();
    }
    public function isPedidoBlocked(array $pedido): bool
    {
        return ($pedido['requiere_aprobacion_comercial'] === 'yes' && $pedido['aprobado_comercial'] === 'no') ||
            ($pedido['requiere_aprobacion_crediticia'] === 'yes' && $pedido['aprobado_crediticio'] === 'no') ||
            $pedido['estado'] === 'eliminado';
    }

    public function buildOrderDetail(array $pedido)
    {
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido['id'])->get()->toarray();
        $peso = 0;
        $tipoImpuesto = [];
        $sumaImpuesto = [];

        foreach ($detalle as &$item) {

            $pesoItem = $item['ART_PESO'] * $item['ART_CANTIDAD'];
            $item['ART_PESO_TOTAL'] = number_format($pesoItem, 2, '.', '');
            $peso += $pesoItem;
            $type = strtoupper(trim((string) ($item['ART_TIPO_COMPRA'] ?? '')));
            $item['compartimiento'] = match ($type) {
                '', 'L', 'SERV' => '',
                default => $this->getCompartimento($item['ART_CODIGO'] ?? null, $pedido['BOD_BODEGA'] ?? null),
            };
            $impKey = $item['ART_IMP_VTA'];
            $tipoImpuesto[$impKey] = ($tipoImpuesto[$impKey] ?? 0) + round($item['ART_VALOR'] * $item['ART_CANTIDAD'], 2);
            $sumaImpuesto[$impKey] = ($sumaImpuesto[$impKey] ?? 0) + round($item['ART_IVA'], 2);
            if (!empty($item['ART_CODIGO_TRES_LETRAS'])) {
                $item['imagenes'] = $this->getImagenes($item['ART_CODIGO_TRES_LETRAS']);
            } else {
                $item['imagenes'] = [];
            }
        }
        return compact('detalle', 'peso', 'tipoImpuesto', 'sumaImpuesto');
    }

    public function getCompartimento($articulo, $bodega)
    {
        $params = array(
            'articulo' => $articulo,
            'bodega' => $bodega,
            'compartimento' => '',
        );
        $response = $this->apiGpService->getStockByWarehouseAndCompartment($params);
        $data = data_get($response, 'data', []);
        return $data['COMPARTIMIENTO'] ?? null;
    }

    public function getImagenes(string $codigo): array
    {
        if (!$codigo)
            return [];

        $imagenes = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codigo}_{$i}.jpg";
            if (File::exists($path)) {
                $imagenes[] = $path;
            }
        }
        return $imagenes;
    }

    public function appendPagoAndClienteInfo(&$pedido)
    {
        $formaPago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $pedido['DESC_FORMA_PAGO_CODIGO'])->first()->toarray();
        $pedido['formadepago'] = $formaPago['DESC_FORMA_PAGO_TIPO_CREDITO'] ?? null;
        $pedido['formadepagoSRI'] = substr($formaPago['DESC_FORMA_PAGO_SRI'] ?? '', 3);

        $cliente = GpCliente::find($pedido['cliente_id'])?->toArray();
        if ($cliente) {
            $emails = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI'] ?? ''));
            $cliente['CLI_CORREO_ELECT_SRI'] = $emails[0] ?? '';
            $pedido['cliente'] = $cliente;
        }

        $sucursal = GpClienteSucursal::find($pedido['sucursal_facturacion_id'])?->toArray();
        $pedido['sucursal'] = $sucursal;
        $pedido['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();
        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $pedido['PTO_VTA_ID'])->first()->toArray();
        $pedido['direccion'] = trim(($ptoVenta['PTO_VTA_DIRECCION_1'] ?? '') . ' ' . ($ptoVenta['PTO_VTA_DIRECCION_2'] ?? ''));
        $pedido['telefono'] = $ptoVenta['PTO_VTA_FONO_1'] ?? '';
    }



    public function generateInvoiceRide($id)
    {
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);
        if ($datosFactura['success'] === false) {
            throw new \Exception($datosFactura['message']);
        }
        $pedido['clave_acceso'] = $datosFactura['data']['claveAcceso'];
        $pedido['fecha_autorizacion'] = Carbon::parse($datosFactura['data']['fechaEnvio'])->format('Y-m-d H:i:s');
        return $pedido;
    }

    public function getInvoiceDocumentInfo($pedido)
    {
        if (!$pedido['factura']) {
            return ['success' => false, 'message' => 'El pedido no tiene factura'];
        }

        return $this->apiSalesService->getDataDocumentGp(array(
            'numdoc' => $pedido['factura'],
        ));
    }

    public function generateInvoiceXml($id)
    {
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);
        if ($datosFactura['success'] === false) {
            throw new \Exception($datosFactura['message']);
        }
        $datos['xml'] = $datosFactura['data']['xml'];
        $datos['factura'] = $pedido['factura'];
        return $datos;
    }



    // PROCESO DE GUARDADO DE PEDIDO

    public function storeOrder($order, $user)
    {

        $this->ensureAllItemsHaveValidQuantity($order['cotizacion']);
        $newOrder = $this->prepareOrderStoreFill($order, $user);
        $this->prepareOrderDetailStoreFill($order['cotizacion'], $newOrder);
        $this->orderAndQuotationService->clearTempDataQuotation($order['sucursal_id'], $order['PTO_VTA_ID'], $order['cliente_id'], $user->id);
        if (isset($newOrder->cotizacion_id) && $newOrder->cotizacion_id != null && $newOrder->cotizacion_id != '') {
            $this->updateValueInvoiceQuotation($newOrder->cotizacion_id);
        }
        $newOrder->refresh();
        /** proceso de limpieza de stock para reservas  de cotizacion */
        $this->cleanStockReservedQuotation($newOrder->cotizacion_id, $newOrder->id, $newOrder->BOD_BODEGA);
        DB::afterCommit(function () use ($newOrder) {
            $this->invoiceService->checkSendInvoice($newOrder);
        });
        return $newOrder;
    }


    public function ensureAllItemsHaveValidQuantity($order)
    {
        foreach ($order as $item) {
            if (empty($item['ART_CANTIDAD']) || $item['ART_CANTIDAD'] <= 0) {
                throw new \InvalidArgumentException("La cantidad del artículo '{$item['ART_CODIGO']}' debe ser mayor a cero.");
            }
        }
    }

    public function prepareOrderStoreFill($order, $user)
    {

        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $order['PTO_VTA_ID'])->first();
        $approvalComercial = $this->approvalService->checkoutApprovalCommercialOrders($order);
        $approvalCredit = $this->approvalService->checkoutApprovalCredit($order);

        $requieresApproval = $approvalCredit != 'no';
        $motivoCrediticio = $requieresApproval ? $approvalCredit : null;
        $requiereAprobacionCrediticia = $requieresApproval ? 'yes' : 'no';
        $fechaEntrega = $order['despacho'] == 'Despacho en ruta' ? $order['fecha_entrega'] : null;
        $bodega = $order['bodega_facturacion'];
        $newOrder = $this->orderBuilder->fromPayloadOrder($order['cotizacion_id'], $order, $approvalComercial, $user, $puntoVenta, $requiereAprobacionCrediticia, $motivoCrediticio, $fechaEntrega, 'cotizacion', null, $bodega);
        $newOrder->save();
        return $newOrder;
    }



    public function prepareOrderDetailStoreFill($detailOrder, $newOrder)
    {
        $usedItemLExcluded = [];
        foreach ($detailOrder as $i => $item) {
            $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
            $hasStock = $this->stockWarehouseService->checkValidateDisponibleItemL($item['ART_CODIGO']);
            if ($isTypeL && !$hasStock) {
                $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
                if (!$replacementCodeL) {
                    throw new \DomainException("No se encontró un artículo tipo L disponible para el código {$item['ART_CODIGO']}");
                }
                $item['ART_CODIGO'] = $replacementCodeL;
                $usedItemLExcluded[] = $replacementCodeL;
            }
            $newOrderDetail = $this->orderBuilder->formPayloadOrderDetail($newOrder->id, $item, $i);
            $newOrderDetail->save();


            if ($item['proveedores']) {
                $this->storeDetailProviders($item['proveedores'], $newOrderDetail, $newOrder);
            }
            if ($item['stock']) {
                $this->storeDetailStock($item['stock'], $newOrderDetail, $newOrder);
            }

            if ($isTypeL) {
                // si te daba deadlock aquí, muévelo más abajo (ver comentario)
                $this->stockWarehouseService->markArticleUnavailable($item['ART_CODIGO']);
            }
        }
    }

    public function storeDetailProviders($providers, $detailOrder, $newOrder)
    {
        foreach ($providers as $provider) {
            $recibido = isset($provider['recibido']) ? $provider['recibido'] : 'no';
            $newProvider = $this->orderBuilder->formPayloadOrderDetailProvider($provider, $newOrder->id, $detailOrder->id, $recibido);
            $newProvider->save();
        }
    }
    public function storeDetailStock($stock, $detailOrder, $newOrder)
    {
        $requiereAprobacion = $newOrder->requiere_aprobacion_crediticia === 'yes'
            || $newOrder->requiere_aprobacion_comercial === 'yes';
        $quoteReservedId = $this->getReservedOriginQuote($newOrder->cotizacion_id);


        foreach ($stock as $item) {
            /** aplicar validacion de stock y actualizar el stock disponible en el pedidodetalles+tock
             *  si no tiene stock almacenarl en un array y retornar como mensaje de error
             */
            $newFormatStock = [
                'STO_ART_STOCK_DISPONIBLE' => $item['STO_ART_STOCK_DISPONIBLE'],
            ];
            if ($item['seleccionado'] > 0) {
                $newFormatStock = $this->orderAndQuotationService->getAndValidateOrderDetailStock($detailOrder, $item, $newOrder->id, $quoteReservedId);
                if (!$newFormatStock) {
                    throw new \DomainException("No se pudo obtener el stock del artículo {$detailOrder['ART_CODIGO']} en la bodega {$item['STO_ART_BODEGA']}");
                }
                if ($item['STO_ART_STOCK_DISPONIBLE'] < $item['seleccionado']) {
                    throw new \DomainException("El stock disponible del artículo {$detailOrder['ART_CODIGO']} en la bodega {$item['STO_ART_BODEGA']} es insuficiente para la cantidad seleccionada.");
                }
            }
            $reservado = $requiereAprobacion ? 0 : $item['seleccionado'];
            $newStock = $this->orderBuilder->formPayloadOrderDetailStock($item, $newOrder->id, $detailOrder->id, $newFormatStock['STO_ART_STOCK_DISPONIBLE'], $reservado);
            $newStock->save();
        }
    }

    public function getReservedOriginQuote(?int $quoteId): ?int
    {
        if (!$quoteId) {
            return null;
        }
        return GpCotizacion::where('id', $quoteId)->where('origen', 'reserva')->value('id');
    }



    public function updateValueInvoiceQuotation($orderId)
    {
        GpCotizacion::where('id', $orderId)->update(['estado' => 'procesado', 'facturado' => 1]);
    }




    public function cleanStockReservedQuotation($quoteId, $orderId, $warehouse)
    {
        $order = GpPedido::with('detallepedido.pedidodetallestocks')->find($orderId);
        if (!$order)
            return;


        $cotResLog = GpCotizacionReservaLog::where('cotizacion_destino_id', $quoteId)->first();
        if (!$cotResLog)
            return;

        $reservedId = data_get($cotResLog, 'reserva_id', null);
        $quoteOriginId = data_get($cotResLog, 'cotizacion_origen_id', null);
        if (!$quoteOriginId)
            return;

        // 1) Consumo del PEDIDO por (ART_CODIGO|BODEGA)
        $pedidoPorClave = []; // clave = ART_CODIGO|BODEGA
        foreach ($order->detallepedido as $det) {
            foreach ($det->pedidodetallestocks as $stk) {
                // if ($warehouse && $stk->STO_ART_BODEGA !== $warehouse) {
                //     continue;
                // }
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
            // ->when($warehouse, fn($q) => $q->where('s.STO_ART_BODEGA', $warehouse))
            ->get();

        if ($quoteStockRows->isEmpty())
            return;

        $cotizacionPorClave = $quoteStockRows->keyBy(fn($row) => $row->ART_CODIGO . '|' . $row->STO_ART_BODEGA);
        $clavesAfectadas = array_values(array_intersect(array_keys($pedidoPorClave), $cotizacionPorClave->keys()->all()));
        if (empty($clavesAfectadas))
            return;

        $idsParaLock = array_map(fn($k) => $cotizacionPorClave[$k]->id, $clavesAfectadas);
        GpCotizacionDetalleStock::whereIn('id', $idsParaLock)->lockForUpdate()->get(); // lo comento por ahora
        // Lock real de filas para evitar race conditions
        foreach ($clavesAfectadas as $k) {
            $row = $cotizacionPorClave[$k];
            $pedidoQty = (int) $pedidoPorClave[$k];

            // a) Reducir seleccionado en la cotización origen: max(origen - pedido, 0)
            $selActual = (int) $row->seleccionado;

            // b) Aumentar facturado pero sin lo facturado - lo cancelado - seleccionado
            $facturado = (int) ($row->cantidad_facturada ?? 0);
            $cancelado = (int) ($row->cantidad_cancelada ?? 0);
            $authorizado = (int) ($row->cantidad_reservada ?? 0);

            // 1) Tope total permitido por política (NO incluye seleccionado)
            $limite = max(0, $authorizado - $cancelado);


            // 2) ¿Cuánto puedo incrementar el facturado SIN pasarme del tope?
            $remanenteFact = max(0, $limite - $facturado);
            $incFacturable = min($pedidoQty, $remanenteFact); //incremento real de factura

            // 3) Limpio seleccionado hasta donde el pedido consuma (prioriza consumir reserva)
            $consumoReserva = min($incFacturable, $selActual);
            $nuevoSelect = $selActual - $consumoReserva;

            // 4) Nuevo total facturado (sin exceder tope)
            $nuevoFacturado = $facturado + $incFacturable;

            if ($nuevoSelect !== $selActual) {
                // Si solo quieres marcar en memoria y guardar luego, puedes batch; aquí guardamos inmediato
                $row->seleccionado = $nuevoSelect; // nunca debe ser negativo
                $row->cantidad_facturada = $nuevoFacturado;
                $row->save();
            }
        }
        $this->updateCheckStatusReservation($reservedId);
    }

    public function updateCheckStatusReservation(int $reserveId)
    {
        $reserva = GpReserva::lockForUpdate()->find($reserveId);
        $stocks = GpCotizacionDetalleStock::query()
            ->where('cotizacion_id', $reserva->cotizacion_id)
            ->get();
        $tienePendientes = $stocks->contains(function (GpCotizacionDetalleStock $stock) {
            return $stock->pendiente > 0;   // usa tu accessor
        });

        if (!$tienePendientes) {
            // Ajusta el nombre de la columna/valor según tu modelo (estado, estado_reserva, etc.)
            $reserva->estado = 'procesado';
            $reserva->save();
        }
    }

    /**
     * ✅ Obtiene el detalle de un pedido especifico.
     *  detallepedido
     *
     */


    public function verifyOrderDispatchOrItemLRecived($orderId)
    {
        $order = GpPedido::find($orderId);
        $existeItemLRecibido = GpPedidoDetalleProveedores::where('pedido_id', $orderId)->where('recibido', 'yes')->exists();
        return [
            'despacho_en_proceso' => $order->despacho_en_proceso,
            'transferencia_en_proceso' => $order->transferencia_en_proceso,
            'item_l_recibido' => $existeItemLRecibido
        ];
    }

    public function detailOrderById($data)
    {
        // 🔍 1. Obtener detalles del pedido
        $detalle = $this->orderRepository->getAllDetailsOrderById($data['id']);

        // 🔍 2. Consultar el crédito del cliente
        $clientQuota = $this->functionsService->getClientCheckAvailableQuota($data['CLI_RUC']);
        // 🔍3. Obtener el segmento del cliente
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
        // 🔍 3.  Enriquecer detalles con proveedores y stock
        $enrichedDetails = $this->enrichOrderDetails($detalle, $data['id'], $data['BOD_BODEGA']);

        // 🔍 4. Obtener órdenes de compra optimizadas
        $ordenes = $this->getPurchaseOrdersWithReceipts($data['pedido']);

        $trasladosVentas = $this->getTransferWithGuides($data['id']);


        // 🔍 6. Obtener despacho, guías y devoluciones
        $orderDespacho = $this->orderRepository->getDispatchOrder($data['id']);
        $guiaRemision = $this->orderRepository->getShipmentGuides($data['pedido']);
        $devoluciones = $this->orderRepository->getReturnsByOrder($data['id']);
        $status = $this->calculateOrderStatus($enrichedDetails, $trasladosVentas);
        return [
            'detalle' => $enrichedDetails['details'],
            'estadodecuenta' => [
                'cupoDisponible' => $clientQuota
            ],
            'ordendecompra' => $ordenes,
            'traslados' => $trasladosVentas,
            'orden_despacho' => $orderDespacho,
            'guias_remision' => $guiaRemision,
            'devoluciones' => $devoluciones,
            'pendientes' => $status['pendientes'],
            'existentes' => $status['existentes'],
            'segmento' => $segmento
        ];
    }


    public function enrichOrderDetails(Collection $details, int $orderId, string $warehouse)
    {
        $hasPending = false;
        $hasExisting = false;
        $itemIds = $details->pluck('id')->toArray();
        $stockByItem = $this->orderRepository->getStockByItemAndWarehouse($itemIds, $warehouse)
            ->keyBy('detalle_id'); // mapeo por detalle_id para acceso rapido
        $details->transform(function ($item) use ($orderId, &$hasPending, &$hasExisting, $stockByItem) {
            $proveedores = $this->functionsService->getItemProviders($orderId, $item, $hasPending, $hasExisting);
            $item->proveedores = $proveedores['proveedores'];
            $hasPending = $proveedores['pendientes'] ?: $hasPending;
            $hasExisting = $proveedores['existentes'] ?: $hasExisting;
            // Obtener stock (desde el map precargado)
            $stock = $stockByItem->get($item->id);
            if ($stock && $stock->seleccionado > 0 && $item->ART_TIPO_COMPRA != 'L') {
                $hasExisting = true;
            }
            $item->priceChange = round($this->orderAndQuotationService->calculateDiscountFraction($item->ART_VALOR_ORIGINAL, $item->ART_VALOR), 3);
            $item->discountChange = round($this->orderAndQuotationService->calculateDiscountChange($item->descuento_original, $item->descuento), 2);

            return $item;
        });

        return [
            'details' => $details,
            'hasPending' => $hasPending,
            'hasExisting' => $hasExisting
        ];
    }

    public function getPurchaseOrdersWithReceipts($orderNumber)
    {
        return $this->orderRepository->getPurchaseOrdersByOrderNumber($orderNumber)
            ->map(function ($orden) {
                $receipt = $this->purchaseReceiptService->setPurcharseOrderdetails($orden);
                return count($receipt->detalle) > 0 ? $receipt : null;
            })
            ->filter()
            ->values();
    }

    public function checkOutValidateDispatchReInvoice($devolucion)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)
            ->where('despachado_parcial', 1)->exists();
    }


    public function getTransferWithGuides(int $orderId): Collection
    {
        $transfers = $this->orderRepository->getTransferOrdersBySale($orderId);
        if ($transfers->isEmpty()) {
            return collect();
        }

        return $transfers->transform(function ($transfer) {
            $transfer->guias = $this->orderRepository->getShipmentGuidesByTransfer($transfer);
            return $transfer;
        });
    }

    public function calculateOrderStatus($details, $transfers)
    {

        $hasPending = $details['hasPending'];
        $hasExisting = $details['hasExisting'];

        if ($transfers->isEmpty()) {
            $hasPending = true;
        } else {
            $transfers->each(function ($transfer) use (&$hasPending, &$hasExisting) {
                $transfer->guias->each(function ($guide) use (&$hasPending, &$hasExisting) {
                    if ($guide->estado != 'recibida') {
                        $hasPending = true;
                    } else {
                        $hasExisting = true;
                    }
                });

                if (!$transfer->completado) {
                    $hasPending = true;
                }
            });
        }

        return [
            'pendientes' => $hasPending,
            'existentes' => $hasExisting
        ];
    }





    /**
     * API PARA LISTAR LOS PEDIDOS SIN COORDENADAS
     *
     */

    public function getLisOrdersWithoutCoordinated()
    {
        $orders = DB::table('tuvalapi.vw_pedidos_sin_coordenadas')->get();

        return $this->successResponse('Información Obtenida Correctamente', $orders, 200);
    }






    /** facturar pedido  */

    public function generateInvoiceOrder($orderId)
    {
        try {
            DB::beginTransaction();
            $order = $this->getOrderOrFail($orderId);
            if (!$this->isInvoiceDateValidForReservedOrder($order)) {
                throw new \DomainException(sprintf(
                    "La fecha de facturación no es válida para pedidos con origen 'reserva'. La fecha del pedido (%s) debe ser igual a la fecha actual (%s).",
                    $order->fe_facturacion_reserva,
                    now()->toDateString()
                ));
            }
            $this->recalculateAndSendInvoice($order);
            DB::commit();
            Log::info("Pedido {$order->pedido} facturado desde Facturar Pedido - generateInvoiceOrder - facturarpedido");
            return $this->buildSuccessResponse("Pedido {$order->pedido} facturado", 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function isInvoiceDateValidForReservedOrder(GpPedido $pedido): bool
    {
        if ($pedido->origen !== 'reserva') {
            return true;
        }

        if (empty($pedido->fe_facturacion_reserva)) {
            return false;
        }

        $today = now()->toDateString();
        $invoiceDate = $pedido->fe_facturacion_reserva instanceof Carbon
            ? $pedido->fe_facturacion_reserva->toDateString()
            : Carbon::parse($pedido->fe_facturacion_reserva)->toDateString();

        return $invoiceDate === $today;
    }


    public function getOrderOrFail($orderId)
    {
        $order = GpPedido::find($orderId);
        if (!$order) {
            return $this->buildErrorResponse("Pedido no encontrado", 404);
        }
        return $order;
    }

    public function recalculateAndSendInvoice($order)
    {
        $this->functionsService->recalculateOrder($order->id);
        $this->invoiceService->sendInvoiceGp($order);
    }

    public function recalculateOrder($order)
    {
        $this->functionsService->recalculateOrder($order->id);
    }



    /** function para recuperar el pedido - volver a pedir  */

    public function restoreOrder($data, $user)
    {
        try {
            $typeCopy = 'pedido';
            $typeRegisterCopy = 'volver_a_pedir';

            DB::beginTransaction();
            $cliente = $this->functionsService->getClient($data);
            $detalle = $this->orderAndQuotationService->getDetailQuotationOrOrder($cliente, $data, false, false, 'pedido');
            $registerCopyId = $this->orderAndQuotationService->createRegisterCopy($data['id'], $user, $typeCopy, $typeRegisterCopy);
            $this->functionsService->recalculateIva($detalle);
            $this->functionsService->setCartFormQuotation($detalle, $data, $user);
            $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
            DB::commit();
            return response([
                'success' => true,
                'cliente' => $cliente,
                'registro_copia' => $registerCopyId,
                'currentSucursal' => $data['sucursal_id'],
                'test' => 'volver_a_pedir',
                'segmento' => $segmento
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in recoverOrder (recuperar pedido - volver a pedir)', [
                'error' => $e->getMessage(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    /*** funcion para copiar pedido  */

    public function copyOrder($data, $user)
    {
        try {
            $typeCopy = ($data['tipo'] == 'cotizacion') ? 'cotizacion' : 'pedido';
            $typeRegisterCopy = 'copia';

            DB::beginTransaction();
            $cliente = $this->functionsService->getClient($data);
            $data['pedido']['sucursal_id'] = $data['sucursal_id'];
            $data['pedido']['cliente_id'] = $data['cliente_id'];
            $detalle = $this->orderAndQuotationService->getDetailQuotationOrOrder($cliente, $data['pedido'], true, true, $typeCopy);
            $registerCopyId = $this->orderAndQuotationService->createRegisterCopy($data['pedido']['id'], $user, $typeCopy, $typeRegisterCopy);
            $segmento = $this->orderAndQuotationService->getSegmentoCliente($data['pedido']['CLI_RUC']);

            $this->functionsService->recalculateIva($detalle);
            $this->functionsService->setCartFormQuotation($detalle, $data['pedido'], $user);
            DB::commit();
            return response([
                'success' => true,
                'cliente' => $cliente,
                'registro_copia' => $registerCopyId,
                'currentSucursal' => $data['sucursal_id'],
                'test' => 'copiarpedido',
                'segmento' => $segmento
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in copyOrder (copiar pedido)', [
                'error' => $e->getMessage(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function buscarItemlTest($item)
    {
        return $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $excludeItemsL = null);
    }


    public function generateOrderDispatch($id)
    {
        try {
            DB::beginTransaction();
            $order = GpPedido::find($id);
            $this->invoiceService->generateDisptachOrder($order);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error generating order dispatch', [
                'error' => $e->getMessage(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function generateNextMonthInvoice()
    {
        $now = now('America/Guayaquil');
        $prev = $now->copy()->subMonthNoOverflow();
        $year = (int) $prev->year;
        $mes = (int) $prev->month;

        if ($this->shouldAbortBilling($mes)) {
            Log::channel('cron_process')->warning("⚠️ No se ejecuta facturación: Aún no llega el mes: {$mes}");
            return [
                'abortado' => true,
                'razon' => "Mes actual no coincide: {$mes}",
                'total' => 0,
                'exitosos' => 0,
                'fallidos' => 0,
                'errores' => []
            ];
        }
        $resultados = ['abortado' => false, 'mes' => $mes, 'total' => 0, 'exitosos' => 0, 'fallidos' => 0, 'errores' => []];
        $this->orderRepository->queryOrdersToInvoiceNextMonth($year, $mes)->chunkById(100, function ($pedidos) use (&$resultados) {
            foreach ($pedidos as $pedido) {
                $resultados['total']++;

                // Éxito idempotente si ya está facturado
                if (filled($pedido->factura) && $pedido->estado === 'facturado') {
                    $resultados['exitosos']++;
                    continue;
                }

                $lock = Cache::lock("pedido:{$pedido->id}:facturacion", 60);
                if (!$lock->get()) {
                    // Otro proceso ya está facturando → no lo cuentes como error
                    Log::channel('cron_process')->info("🔒 Lock activo, omito pedido {$pedido->pedido}");
                    continue;
                }
                try {
                    try {

                        $this->invoiceService->checkSendInvoice($pedido, true, false);
                    } catch (\Throwable $exGp) {
                        // Clasifica “ya facturado” como éxito idempotente
                        $msg = mb_strtolower($exGp->getMessage());
                        if (str_contains($msg, 'ya se encuentra facturado') || str_contains($msg, 'ya existe')) {
                            $resultados['exitosos']++;
                            Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} ya estaba facturado (idempotente)");
                        } else {
                            throw $exGp;
                        }
                    }

                    $pedido->refresh();
                    if ($pedido->estado === 'facturado' && filled($pedido->factura)) {
                        $resultados['exitosos']++;
                        Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} facturado: {$pedido->factura}");
                    } else {
                        // throw new \Exception("El pedido no cambió a 'facturado'");
                        Log::channel('cron_process')->info("✅ Pedido {$pedido->pedido} no cambió a 'facturado'");
                    }
                } catch (\Throwable $e) {
                    $resultados['fallidos']++;
                    $resultados['errores'][] = [
                        'pedido_id' => $pedido->id,
                        'pedido' => $pedido->pedido,
                        'error' => $e->getMessage(),

                    ];
                    Log::channel('cron_process')->error("❌ Error facturando pedido {$pedido->pedido}", [
                        'pedido_id' => $pedido->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),

                    ]);
                } finally {
                    optional($lock)->release();
                }
            }
        });

        Log::channel('cron_process')->info("📊 Resumen facturación masiva", $resultados);
        return $resultados;
    }

    public function shouldAbortBilling($mes)
    {
        return $mes === date("m");
    }


    /** funcion para actualizar pedido,
     * Ya que al momento en que guardo un pedido se envió a facturar
     * y se enviaron todos los items de la cotización y no como tal solo lo del pedido
     *
     */


    public function updateOrderAndRecalculate($orderId)
    {
        try {
            DB::beginTransaction();
            $order = GpPedido::find($orderId);
            //datos de cotizacion
            $dataQuotation = $this->getDataQuotationToUpdateOrder($order->cotizacion_id);
            $newOrder = $this->fillOrderHeader($order, $dataQuotation);


            foreach ($dataQuotation['detallecotizacion'] as $i => $item) {
                $detail = GpPedidoDetalle::where('pedido_id', $orderId)->where('ART_CODIGO', $item['ART_CODIGO'])
                    ->where('ART_CANTIDAD', $item['ART_CANTIDAD'])->first();
                if (!$detail) { //crea
                    $detail = $this->prepareOrderDetailToCreate($newOrder, $item, $i);
                    $detail->save();
                } else {
                    //actualiza
                    $fields = $this->prepareOrderDetailToUpdate($newOrder, $item, $detail, $i);
                    $detail->fill($fields);
                    $detail->save();
                }
                $this->syncDetailProvidersUpdateOrCreate($newOrder, $detail->id, $item['proveedores'] ?? []);
                $this->syncDetailStockUpdateOrCreate($newOrder, $detail, $item['stock'] ?? []);
            }
            $this->functionsService->recalculateOrder($orderId);
            DB::commit();
            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in updateOrderAndRecalculate (actualizar pedido)', [
                'error' => $e->getMessage(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function getDataQuotationToUpdateOrder($quotationId)
    {
        $data = GpCotizacion::with([
            'detallecotizacion.proveedores',
            'detallecotizacion.stock',
        ])->find($quotationId);
        return $data->toArray();
    }

    public function fillOrderHeader(GpPedido $order, $dataQuotation)
    {

        $order->fill([
            'cotizacion_id' => $dataQuotation['id'],
            'porcentage_descuentooriginal' => $dataQuotation['porcentage_descuentooriginal'],
            'porcentage_descuento' => $dataQuotation['porcentage_descuento'],
            'subtotal' => number_format((float) $dataQuotation['subtotal'] + (float) $dataQuotation['descuento'], 2, '.', ''),
            'descuento' => number_format((float) $dataQuotation['descuento'], 2, '.', ''),
            'subtotalmenosdescuento' => number_format((float) $dataQuotation['subtotal'], 2, '.', ''),
            'iva' => number_format((float) $dataQuotation['iva'], 2, '.', ''),
            'total' => number_format((float) $dataQuotation['total'], 2, '.', ''),
        ]);

        $order->save();
        return $order;
    }

    public function prepareOrderDetailToCreate($newOrder, $item, $index)
    {
        Log::info('Item recibido para creacion: ' . $item['ART_CODIGO'] . 'DE PEDIDO :' . 'pedido_id :' . $newOrder->id);
        $usedItemLExcluded = [];
        $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
        $hasStock = $this->stockWarehouseService->checkArticuloDisponible($item);
        if ($isTypeL && !$hasStock) {
            $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
            if (!$replacementCodeL) {
                Log::warning('No se encontró un item L disponible para el pedido ', [
                    'pedido_id' => $newOrder->id,
                    'articulo' => $item['ART_CODIGO'] ?? null
                ]);
                throw new \Exception("No se encontró un artículo tipo L disponible para el código {$item['ART_CODIGO']}");
            }
            $item['ART_CODIGO'] = $replacementCodeL;
            $usedItemLExcluded[] = $replacementCodeL;
        }
        return new GpPedidoDetalle($this->mapDetailFielsToUpdateOrCreated($newOrder, $item, $index));
    }

    public function prepareOrderDetailToUpdate($newOrder, $item, $detail, $index)
    {
        Log::info('Detalle recibido para actualizacion: ' . $detail['ART_CODIGO'] . 'DE PEDIDO :' . 'pedido_id :' . $newOrder->id);
        $usedItemLExcluded = [];
        $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
        $hasStock = $this->stockWarehouseService->checkArticuloDisponible($item);
        if ($isTypeL && !$hasStock) {
            $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
            $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
            if (!$replacementCodeL) {
                Log::warning('No se encontró un item L disponible para el pedido ', [
                    'pedido_id' => $newOrder->id,
                    'articulo' => $item['ART_CODIGO'] ?? null
                ]);
                throw new \Exception("No se encontró un artículo tipo L disponible para el código {$item['ART_CODIGO']}");
            }
            $item['ART_CODIGO'] = $replacementCodeL;
            $usedItemLExcluded[] = $replacementCodeL;
        }
        return $this->mapDetailFielsToUpdateOrCreated($newOrder, $item, $index);
    }

    public function mapDetailFielsToUpdateOrCreated($newOrder, $item, $i)
    {
        return [
            'pedido_id' => $newOrder->id,
            'orden' => $i + 1,
            'ART_CODIGO' => $item['ART_CODIGO'],
            'ART_DESCRIPCION' => $item['ART_DESCRIPCION'],
            'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD' => (int) $item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA' => $item['ART_MARCA'],
            'ART_MATERIAL' => $item['ART_MATERIAL'],
            'ART_PESO' => $item['ART_PESO'],
            'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
            'ART_VALOR' => (float) $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL' => (float) $item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO' => (float) $item['ART_DESCUENTO'],
            'ART_IVA' => (float) $item['ART_IVA'],
            'ART_CANTIDAD' => (float) $item['ART_CANTIDAD'],
            'ART_STOCK' => (float) $item['ART_STOCK'],
            'ART_IMP_VTA' => (float) $item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO' => (float) $item['ART_COSTO_PROMEDIO'],
            'descuento' => (float) $item['descuento'],
            'descuento_original' => (float) $item['descuento_original'],
            'modificador' => (float) $item['modificador'],
        ];
    }

    public function syncDetailProvidersUpdateOrCreate($newOrder, $detailOrderId, $providers)
    {
        $incoming = [];
        foreach ($providers as $prov) {
            $recibido = isset($prov['recibido']) ? $prov['recibido'] : 'no';
            $model = GpPedidoDetalleProveedores::updateOrCreate(
                [
                    'pedido_id' => $newOrder->id,
                    'pedido_detalle_id' => $detailOrderId,
                    'ID_PROVEEDOR' => $prov['ID_PROVEEDOR']
                ],
                [
                    'DESCRIPCION_ARTICULO' => $prov['DESCRIPCION_ARTICULO'],
                    'FECHA_ENTREGA' => $prov['FECHA_ENTREGA'],
                    'CANTIDAD' => $prov['CANTIDAD'],
                    'COSTO_UNITARIO' => $prov['COSTO_UNITARIO'],
                    'IMPUESTO' => $prov['IMPUESTO'],
                    'TOTAL' => $prov['TOTAL'],
                    'TOTALCONIMPUESTO' => $prov['TOTALCONIMPUESTO'],
                    'NOTA' => $prov['NOTA'],
                    'recibido' => $recibido,
                    'MARGEN' => $prov['MARGEN'],
                ]
            );
            $incoming[] = $model->id;
        }

        GpPedidoDetalleProveedores::where('pedido_id', $newOrder->id)
            ->where('detalle_id', $detailOrderId)
            ->whereNotIn('id', $incoming)
            ->delete();
    }
    public function syncDetailStockUpdateOrCreate(GpPedido $newOrder, GpPedidoDetalle $detailOrder, array $stock)
    {

        $incoming = [];
        foreach ($stock as $item) {
            $model = GpPedidoDetalleStock::updateOrCreate([
                'pedido_id' => $newOrder->id,
                'detalle_id' => $detailOrder->id,
                'STO_ART_BODEGA' => $item['STO_ART_BODEGA'],
                // 'seleccionado' => $item['seleccionado'],
            ], [
                'STO_ART_STOCK_DISPONIBLE' => $item['STO_ART_STOCK_DISPONIBLE'], // me devuelve el stock disponile
                'seleccionado' => $item['seleccionado'],
                'reservado' => $item['seleccionado'],
            ]);
            // Añado su ID a la lista de entrantes
            $incoming[] = $model->id;
        }

        // Por último, borro los stocks que ya no estuvieron en el payload
        GpPedidoDetalleStock::where('pedido_id', $newOrder->id)
            ->where('detalle_id', $detailOrder->id)
            ->whereNotIn('id', $incoming)
            ->delete();
    }




    /***
     *   PROCESO DE FACTURAR PEDIDO EXISTENTE
     *
     */

    public function checkExistence($data)
    {
        try {
            $subtotal = 0.0;
            $totalDiscount = 0.0;
            $netSubtotal = 0.0;
            $totalIva = 0.0;

            $detailOrder = $this->orderRepository->getAllDetailsOrderById($data['id']);
            $stocks = $this->orderRepository->getStockOrderDetailForCheckExistence($data['BOD_BODEGA'], $detailOrder->pluck('id'));
            $impVtaByCodigo = GpArticulo::query()
                ->whereIn('ART_CODIGO', $detailOrder->pluck('ART_CODIGO'))
                ->pluck('ART_IMP_VTA', 'ART_CODIGO');

            $processed = $detailOrder->map(function ($item) use ($data, $impVtaByCodigo, $stocks, &$subtotal, &$totalDiscount, &$netSubtotal, &$totalIva) {
                $stock = $stocks->get($item->id);
                $cantidad = 0;

                if ($stock && $item->ART_TIPO_COMPRA != 'L' && $item->ART_TIPO_COMPRA != 'SERV') {
                    $cantidad = $stock->seleccionado;
                }
                if ($item->ART_TIPO_COMPRA == 'SERV') {
                    $cantidad = $item->ART_CANTIDAD;
                }
                $providers = $this->functionsService->getItemProviders($data['id'], $item, false);

                $validProviders = [];
                if ($providers['proveedores']->isNotEmpty()) {
                    $cantidad = 0;
                    foreach ($providers['proveedores'] as $prov) {
                        $diff = $prov->ingresado - $prov->ejecutado;
                        if ($prov->ingresado > 0) {
                            $cantidad += $diff;
                            $validProviders[] = $prov;
                        }
                    }
                }

                if ($cantidad <= 0) {
                    return null;
                }

                $item->stocks = $stock;
                $item->proveedores = $validProviders;

                //CALCULOS
                $valorUnitario = (float) $item->ART_VALOR;
                $item->ART_CANTIDAD = (float) $cantidad;
                $descuentoUnitario = (float) $item->ART_DESCUENTO;

                $lineaSubtotal = $valorUnitario * (float) $cantidad;
                $lineaDiscount = $descuentoUnitario * (float) $cantidad;
                $lineNet = round(($valorUnitario - $descuentoUnitario) * (float) $cantidad, 2);
                $impuestoPorcentaje = $impVtaByCodigo[$item->ART_CODIGO] ?? 0;
                $lineaIva = round($lineNet * ($impuestoPorcentaje / 100), 3);

                // calculos monetarios
                $subtotal += $lineaSubtotal;
                $totalDiscount += $lineaDiscount;
                $netSubtotal += $lineNet;
                $totalIva += $lineaIva;

                $item->subtotal = $lineaSubtotal;
                $item->ART_IMP_VTA = $impuestoPorcentaje;
                $item->ART_IVA = round($lineaIva, 2);
                return $item;
            })
                ->filter()
                ->values();
            $total = round($netSubtotal + $totalIva, 3);
            $data['subtotal'] = round($subtotal, 3);
            $data['descuento'] = round($totalDiscount, 3);
            $data['subtotalmenosdescuento'] = round($netSubtotal, 3);
            $data['iva'] = round($totalIva, 3);

            $data['total'] = $total;


            $facturarExistente = [];
            $facturarExistente['pedido'] = $data;
            $facturarExistente['detalle'] = $processed;

            return response([
                'success' => true,
                'facturar_existente' => $facturarExistente,
            ]);
        } catch (\Throwable $e) {
            // Log interno
            Log::error('Error en checkExistence', ['exception' => $e]);
            // Respuesta genérica
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al comprobar la existencia.'
            ], 500);
        }
    }


    public function generateInvoiceFromExistingOrder($data, $detalle, $user)
    {
        $deteleArticle = [];
        $detailIdsWithLOrSERV = GpPedidoDetalle::where('pedido_id', $data['id'])
            ->whereIn('ART_TIPO_COMPRA', ['SERV', 'L'])
            ->get();
        $detailIdsWithoutLOrSERV = GpPedidoDetalle::where('pedido_id', $data['id'])
            ->whereNotIn('ART_TIPO_COMPRA', ['SERV', 'L'])
            ->get();

        $stockByDetalle = GpPedidoDetalleStock::whereIn('detalle_id', $detailIdsWithoutLOrSERV->pluck('id'))
            ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])->get()
            ->keyBy('detalle_id');


        // eliminacion de item con stock->selecccionado == cantidad y sin L o SERV
        foreach ($detailIdsWithoutLOrSERV as $articulo) {
            $stock = $stockByDetalle->get($articulo->id);

            // si no tiene stock lo almacena en array sigue
            if (!$stock) {
                // $skippedDetails[] = $articulo->id;
                continue;
            }

            // // Si seleccionado == cantidad -> omitir (no eliminar la línea original)
            if ((int) $stock->seleccionado === (int) $articulo->ART_CANTIDAD) {
                $this->clearStockArticleOrderExistence($data['id'], $articulo->id);
                $articulo->delete();
                continue;
            }

            // si el seleccionado es mayor a 0 y si es menor que ART_CANTIDAD RESTA para actualizar el nuevo detalle
            if ($stock->seleccionado > 0 && $stock->seleccionado < $articulo->ART_CANTIDAD) {
                $articulo->ART_CANTIDAD = $articulo->ART_CANTIDAD - $stock->seleccionado;
                $stock->seleccionado = 0;
                $stock->reservado = 0;
                $articulo->save();
                $stock->save();
            }
        }

        //eliminacion de items SERV y L de pedido original
        foreach ($detailIdsWithLOrSERV as $artL) {
            if ($artL->ART_TIPO_COMPRA == 'SERV') {
                $artL->delete();
                continue;
            }
            $providers = $this->functionsService->getItemProviders($data['id'], $artL, false);
            $provList = $providers['proveedores'] ?? collect();
            if ($provList->isEmpty()) {
                continue;
            }

            foreach ($provList as $prov) {
                $pendiente = max(0, (int) $prov->ingresado - (int) $prov->ejecutado);
                if ($pendiente <= 0)
                    continue;

                $toDeduct = min((int) $artL->ART_CANTIDAD, $pendiente);
                if ($toDeduct <= 0)
                    break;

                $artL->ART_CANTIDAD = (int) $artL->ART_CANTIDAD - $toDeduct;
            }

            if ($provList->isNotEmpty()) {
                if ($artL->ART_CANTIDAD == 0) {
                    $deteleArticle['articulos'][] = $artL;
                } else {
                    $artL->save();
                }
            }
        }

        $newOrder = $this->prepareToStoreOrderHeaderExistence($data, $user);
        $this->storeFillDetailOrderExistence($detalle, $newOrder);
        $this->deleteArticlesForNewOrder($deteleArticle);
        $this->recalculateOldAndNewOrder($data['id'], $newOrder->id);
        DB::afterCommit(function () use ($newOrder) {
            $this->invoiceService->checkSendInvoice($newOrder, true);
        });
        Log::info('Pedido desde Facturar Existente Generado Correctamente en generateInvoiceFromExistingOrder', [
            'pedido' => $newOrder->pedido
        ]);
        return $newOrder;
    }


    public function prepareToStoreOrderHeaderExistence($data, $user)
    {
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $newOrder = new GpPedido();
        $newOrder->fill([
            'cotizacion_id' => $data['cotizacion_id'],
            'user_id' => $user->id,
            'sucursal_id' => $data['sucursal_id'],
            'sucursal_facturacion_id' => $data['sucursal_facturacion_id'],
            'cliente_id' => $data['cliente_id'],
            'factura_posterior_despacho' => $data['factura_posterior_despacho'],
            'factura_a_demanda' => $data['factura_a_demanda'],
            'factura_siguiente_mes' => $data['factura_siguiente_mes'],
            'impuesto' => env('IVA_IMPUESTO'),
            'BOD_BODEGA' => $data['BOD_BODEGA'],
            'PTO_VTA_ID' => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION' => $puntoVenta->PTO_VTA_DESCRIPCION,
            'USERID' => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO' => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC' => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL' => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI' => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID' => $data['COND_ID'],
            'COND_ID_ORIGINAL' => $data['COND_ID_ORIGINAL'],
            'VEND_ID' => $data['VEND_ID'],
            'VEND_NOMBRES' => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS' => $data['VEND_APELLIDOS'],
            'despacho' => $data['despacho'],
            'porcentage_descuentooriginal' => $data['porcentage_descuentooriginal'],
            'porcentage_descuento' => $data['porcentage_descuento'],
            'descuento' => number_format((float) $data['descuento'], 2, '.', ''),
            'subtotal' => number_format((float) $data['subtotal'] + (float) $data['descuento'], 2, '.', ''),
            'subtotalmenosdescuento' => number_format((float) $data['subtotal'], 2, '.', ''),
            'iva' => number_format((float) $data['iva'], 2, '.', ''),
            'total' => number_format((float) $data['total'], 2, '.', ''),
            'requiere_aprobacion_comercial' => $data['requiere_aprobacion_comercial'],
            // 'motivo_aprobacion_comercial' => $data['motivo'],
            'aprobado_comercial' => $data['aprobado_comercial'],
            'aprobador_comercial_id' => $data['aprobador_comercial_id'],
            'requiere_aprobacion_crediticia' => $data['requiere_aprobacion_crediticia'],
            'aprobado_crediticio' => $data['aprobado_crediticio'],
            'aprobador_crediticio_id' => $data['aprobador_crediticio_id'],
            // 'motivo_crediticio' => $motivoCrediticio,
            'nota_interna' => $data['nota_interna'],
            'nota_cliente' => $data['nota_cliente'],
            'orden_de_compra' => $data['orden_de_compra'],
            'pedido_origen' => $data['pedido'],
        ]);
        $newOrder->save();
        $newOrder->pedido = 'PED' . str_pad($newOrder->id, 8, "0", STR_PAD_LEFT);
        $newOrder->save();
        Log::info('Pedido creado con exito desde Facturar existente: ' . $newOrder->pedido);
        return $newOrder;
    }

    public function storeFillDetailOrderExistence($detailOrder, $newOrder)
    {

        foreach ($detailOrder as $i => $item) {
            $newOrderDetail = new GpPedidoDetalle();
            $newOrderDetail->fill([
                'pedido_id' => $newOrder->id,
                'orden' => $i + 1,
                'ART_CODIGO' => $item['ART_CODIGO'],
                'ART_DESCRIPCION' => $item['ART_DESCRIPCION'],
                'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
                'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
                'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
                'ART_DECIMAL_CANTIDAD' => $item['ART_DECIMAL_CANTIDAD'],
                'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
                'ART_MARCA' => $item['ART_MARCA'],
                'ART_MATERIAL' => $item['ART_MATERIAL'],
                'ART_PESO' => $item['ART_PESO'],
                'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
                'ART_VALOR' => $item['ART_VALOR'],
                'ART_VALOR_ORIGINAL' => $item['ART_VALOR_ORIGINAL'],
                'ART_DESCUENTO' => $item['ART_DESCUENTO'],
                'ART_IVA' => $item['ART_IVA'],
                'ART_CANTIDAD' => $item['ART_CANTIDAD'],
                'ART_STOCK' => $item['ART_STOCK'],
                'ART_IMP_VTA' => $item['ART_IMP_VTA'],
                'ART_COSTO_PROMEDIO' => $item['ART_COSTO_PROMEDIO'],
                'descuento' => $item['descuento'],
                'descuento_original' => $item['descuento_original'],
                'modificador' => $item['modificador'],
            ]);
            $newOrderDetail->save();
            if ($item['ART_TIPO_COMPRA'] != 'SERV' && count($item['proveedores']) > 0) {
                $this->storeFillProviderDetailExistence($item['proveedores'], $newOrderDetail, $newOrder, $item);
            }

            if ($item['stocks']) {
                $this->storeDetailStockExistence($item['stocks'], $newOrder->id, $newOrderDetail->id);
            }
        }
    }

    public function storeFillProviderDetailExistence($providers, $newOrderDetail, $newOrder, $oldDetailOrder)
    {
        foreach ($providers as $provider) {
            $newProv = new GpPedidoDetalleProveedores();
            $newProv->fill([
                'pedido_id' => $newOrder->id,
                'detalle_id' => $newOrderDetail->id,
                'ID_PROVEEDOR' => $provider['ID_PROVEEDOR'],
                'DESCRIPCION_ARTICULO' => $provider['DESCRIPCION_ARTICULO'],
                'FECHA_ENTREGA' => $provider['FECHA_ENTREGA'],
                'CANTIDAD' => $provider['CANTIDAD'],
                'COSTO_UNITARIO' => $provider['COSTO_UNITARIO'],
                'IMPUESTO' => $provider['IMPUESTO'],
                'TOTAL' => $provider['TOTAL'],
                'TOTALCONIMPUESTO' => $provider['TOTALCONIMPUESTO'],
                'NOTA' => $provider['NOTA'],
                'recibido' => "yes", // se asigna recibido porque ya hay items que se recibieron y seran despachados para que no me genere el pedido en orden de compra
                'MARGEN' => $provider['MARGEN']
            ]);
            $newProv->save();

            if ($provider['orden_detalle']) {
                $this->storeOrUpdateOrderSalesProvider($provider['orden_detalle'], $oldDetailOrder, $provider);
            }
        }
    }


    public function storeOrUpdateOrderSalesProvider($orderSales, $oldDetailOrder, $oldProvider)
    {
        foreach ($orderSales as $ordC) {
            $ordenCompra = GpOrdenDeCompra::where('DOC_GENERADO', $ordC['ORDEN_COMPRA_DOCUMENTO'])
                ->where('detalle_id', $oldDetailOrder['id'])
                ->where('detalle_proveedores_id', $oldProvider['id'])
                ->lockForUpdate()
                ->first();

            if (!$ordenCompra) {
                continue;
            }

            $oldProv = GpPedidoDetalleProveedores::query()
                ->whereKey($oldProvider['id'])
                ->lockForUpdate()
                ->first();

            if (!$oldProv) {
                continue;
            }

            $delta = (int) ($oldDetailOrder['ART_CANTIDAD'] ?? 0);
            if ($delta <= 0) {
                break;
            }
            $prev = (int) ($oldProv->ejecutado ?? 0);
            $cap = (int) ($oldProv->CANTIDAD ?? 0);

            if ($cap <= 0 || $prev >= $cap) {
                break; // ya completo o sin cantidad base
            }
            $oldProv->ejecutado = min($cap, $prev + $delta);
            $oldProv->save();


            $ordCGD = GpOrdenDeCompraGenerado::where('orden_id', $ordenCompra->id)->first();
            if (!$ordCGD) {
                continue;
            }
            if ($oldProv->ejecutado >= $cap) {
                $ordCGD->estado = 'recibido';
                $ordCGD->save();
            }
        }
    }


    public function storeDetailStockExistence($stock, $newOrderId, $newDetailId)
    {

        $nwStock = new GpPedidoDetalleStock();
        $nwStock->fill(attributes: [
            'pedido_id' => $newOrderId,
            'detalle_id' => $newDetailId,
            'STO_ART_BODEGA' => $stock['STO_ART_BODEGA'],
            'STO_ART_STOCK_DISPONIBLE' => $stock['STO_ART_STOCK_DISPONIBLE'],
            'seleccionado' => $stock['seleccionado'],
            'reservado' => $stock['reservado'],
        ]);
        $nwStock->save();
    }

    public function deleteArticlesForNewOrder($deleteItems)
    {
        $deleteItems['articulos'] = $deleteItems['articulos'] ?? [];
        if (!empty($deleteItems['articulos'])) {
            $this->deleteArticlesOldOrder($deleteItems['articulos']);
        }
    }

    public function deleteProvidersOldOrder($providers)
    {
        $providers = $providers instanceof Collection ? $providers : collect($providers);
        $providers->filter()->each->delete();
    }

    public function deleteArticlesOldOrder($articles)
    {
        $articles = $articles instanceof Collection ? $articles : collect($articles);
        $articles->filter()->each->delete();
    }

    public function recalculateOldAndNewOrder($oldOrderId, $newOrderId)
    {
        $this->functionsService->recalculateOrder($oldOrderId);
        $this->functionsService->recalculateOrder($newOrderId);
    }

    public function clearStockArticleOrderExistence($orderId, $detailId)
    {
        GpPedidoDetalleStock::where('detalle_id', $detailId)
            ->where('pedido_id', $orderId)->delete();
    }


    public function syncPurchaseOrderAndNoteByOrder(int $id, string $newOrd, string $newNote)
    {
        $ord = is_null($newOrd) ? '' : trim($newOrd);
        $note = is_null($newNote) ? '' : trim($newNote);
        $Order = GpPedido::find($id);
        if (!$ord) {
            throw new \Exception('El pedido no existe');
        }
        $Order->orden_de_compra = $ord;
        $Order->nota_cliente = $note;
        $Order->save();
        return $Order;
    }

    public function updateOrderReservationDate(int $id, string $newDate)
    {
        $order = GpPedido::find($id);
        if (!$order) {
            throw new \Exception("El pedido con ID {$id} no existe.");
        }
        $order->fe_facturacion_reserva = $newDate ? Carbon::parse($newDate) : null;
        $order->save();
        return $order;
    }
}
