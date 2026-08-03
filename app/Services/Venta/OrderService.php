<?php

namespace App\Services\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\OrderStatus;
use App\Enums\Ventas\QuotationMode;
use App\Models\aprobacion\GpReglaAprobacion;
use App\Models\despachos\GpGuiaRemisionDespacho;
use App\Models\Articulos\GpArticulo;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Carbon\Carbon;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpPedidoImagen;
use App\Models\GpUsuario;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Models\traslados\GpTrasladosVentas;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use App\Services\Api\ApiSalesService;
use App\Services\Api\Transacciones\ApiGpReceivedItemL;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class OrderService implements OrderServiceInterface
{
    use JsonResponseTrait;


    public function __construct(
        protected readonly ApiSalesService $apiSalesService,
        protected readonly ApiGpService $apiGpService,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly FunctionsServiceInterface $functionsService,
        protected readonly PurchaseReceiptServiceInterface $purchaseReceiptService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly OrderBuilderInterface $orderBuilder,
        protected readonly ApiGpReceivedItemL $apiGpReceiptService,
        protected readonly ClientQueryServiceInterface $clientService,
        protected readonly ApprovalFunctionsServiceInterface $approvalFuntions,

    ) {}

    //me obtiene la informacion del documento xml_notificaciones



    public function getOrderInfo(int $id)
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

    public function getOrderInfoById(int $id)
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
            $pedido['estado'] === OrderStatus::ELIMINADO->value;
    }

    public function buildOrderDetail(array $pedido)
    {
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido['id'])->get()->toarray();
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', collect($detalle)->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        $peso = 0;
        $tipoImpuesto = [];
        $sumaImpuesto = [];

        foreach ($detalle as &$item) {

            $cantidad = $item['ART_CANTIDAD'];
            $valorUnitario = $item['ART_VALOR'];
            $descuentoUnitario = $item['ART_DESCUENTO'];
            $impuestoPorcentaje = (float) $articulosImpuesto[$item['ART_CODIGO']] ?? 0;
            $typeImp = $item['ART_IMP_VTA'];


            //calculo de peso por item
            $pesoItem = $item['ART_PESO'] * $item['ART_CANTIDAD'];
            $item['ART_PESO_TOTAL'] = number_format($pesoItem, 2, '.', '');
            $peso += $pesoItem;

            //compartimento
            $type = strtoupper(trim((string) ($item['ART_TIPO_COMPRA'] ?? '')));
            $item['compartimiento'] = match ($type) {
                '', 'L', 'SERV' => '',
                default => $this->getCompartimento($item['ART_CODIGO'] ?? null, $pedido['BOD_BODEGA'] ?? null),
            };

            //calculo de subtotales

            $lineNet = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);

            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);

            $tipoImpuesto[$typeImp] = ($tipoImpuesto[$typeImp] ?? 0) + $lineaSubtotal;
            $sumaImpuesto[$typeImp] = ($sumaImpuesto[$typeImp] ?? 0) + $lineaIva;
            if (!empty($item['ART_CODIGO_TRES_LETRAS'])) {
                $item['imagenes'] = $this->getImagenes($item['ART_CODIGO_TRES_LETRAS']);
            } else {
                $item['imagenes'] = [];
            }
        }
        return compact('detalle', 'peso', 'tipoImpuesto', 'sumaImpuesto');
    }

    public function getCompartimento(string $articulo, string $bodega)
    {
        $params = array(
            'articulo'      => $articulo,
            'bodega'        => $bodega,
            'compartimento' => '',
        );
        $response = $this->apiGpService->getStockByWarehouseAndCompartment($params);
        $data = data_get($response, 'data', []);
        return data_get($data, 'COMPARTIMIENTO', null);
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



    public function generateInvoiceRide(int $id)
    {
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);

        $pedido['clave_acceso'] = data_get($datosFactura, 'claveAcceso', '');
        $pedido['fecha_autorizacion'] = Carbon::parse(data_get($datosFactura, 'fechaEnvio'))->format('Y-m-d H:i:s');
        return $pedido;
    }

    public function getInvoiceDocumentInfo($pedido)
    {
        if (!$pedido['factura']) {
            throw new \Exception('El pedido no tiene factura');
        }

        $resp = $this->apiSalesService->fetchGpInvoiceInfo(array(
            'numdoc' => $pedido['factura'],
        ));
        $success = data_get($resp, 'success', false);
        $message = data_get($resp, 'message', 'Error al obtener datos del documento');
        if (!$success) {
            throw new \Exception($message);
        }
        return data_get($resp, 'data', []);
    }

    public function generateInvoiceXml(int $id)
    {
        $pedido = $this->getOrderInfo($id);
        $datosFactura = $this->getInvoiceDocumentInfo($pedido);

        $datos['xml'] =  data_get($datosFactura, 'xml', '');
        $datos['factura'] =  data_get($datosFactura, 'numeroDocumento', '');
        return $datos;
    }



    /**
     * ✅ Obtiene el detalle de un pedido especifico.
     *  detallepedido
     *
     */


    public function verifyOrderDispatchOrItemLRecived(int $orderId)
    {
        $order = GpPedido::find($orderId);
        $existeItemLRecibido = GpPedidoDetalleProveedores::where('pedido_id', $orderId)->where('recibido', 'yes')->exists();
        return [
            'despacho_en_proceso'       => $order->despacho_en_proceso,
            'transferencia_en_proceso'  => $order->transferencia_en_proceso,
            'item_l_recibido'           => $existeItemLRecibido
        ];
    }

    public function detailOrderById(array $data, GpUsuario $user)
    {
        $orderHeader            = GpPedido::where('id', $data['id'])->first();
        $reglasAprobacion       = $this->getPorcentilChangeDiscount($user, $orderHeader);
        $detalle                = $this->orderRepository->getAllDetailsOrderById($data['id']);
        $clientQuota            = $this->functionsService->getClientCheckAvailableQuota($data['CLI_RUC']);
        $segmento               = $this->orderAndQuotationService->getSegmentoCliente($data['CLI_RUC']);
        $enrichedDetails        = $this->enrichOrderDetails($detalle, $data['id'], $data['BOD_BODEGA'],  $reglasAprobacion);
        $ordenes                = $this->getPurchaseOrdersWithReceipts($data['pedido']);
        $trasladosVentas        = $this->getTransferWithGuides($data['id']);
        $hasGuideDispatched     = $this->orderRepository->queryHasGuideDispatched($data['id']);
        $orderDespacho          = $this->orderRepository->getDispatchOrder($data['id']);
        $guiaRemision           = $this->orderRepository->getShipmentGuides($data['pedido']);
        $devoluciones           = $this->orderRepository->getReturnsByOrder($data['id']);
        $status                 = $this->calculateOrderStatus($enrichedDetails, $trasladosVentas);
        $nivelPrecio            = GpCliente::where('CLI_RUC', $data['CLI_RUC'])->value('CLI_NIVEL_PRECIO');
        $imagenesOperaciones    = $this->orderAndQuotationService->getAllImagensOperations($data['id'], 'pedido');
        $pedido                 = GpPedido::find($data['id']);
        $documentosPdf          = $this->orderAndQuotationService->getFormattedPdfMedia($pedido);
        return [
            'detalle' => $enrichedDetails['details'],
            'estadodecuenta' => [
                'cupoDisponible' => $clientQuota
            ],
            'ordendecompra'         => $ordenes,
            'traslados'             => $trasladosVentas,
            'orden_despacho'        => $orderDespacho,
            'guias_remision'        => $guiaRemision,
            'devoluciones'          => $devoluciones,
            'pendientes'            => $status['pendientes'],
            'existentes'            => $status['existentes'],
            'segmento'              => $segmento,
            'has_guide_dispatched'  => $hasGuideDispatched,
            'nivelPrecio'           => $nivelPrecio,
            'imagenesOperaciones'   => $imagenesOperaciones,
            'documentosPdf'         => $documentosPdf
        ];
    }

    /** detalle de pedido para visualizar en lista de crediticio */
    public function getOrderDetailStatement(int $id, GpUsuario $user): array
    {
        $order              = GpPedido::find($id);
        $rulesApproval      = $this->getPorcentilChangeDiscount($user, $order);
        $detalle            = $this->orderRepository->getAllDetailsOrderById($order->id);
        $segmento           = $this->orderAndQuotationService->getSegmentoCliente($order->CLI_RUC);
        $enrichedDetails    = $this->enrichOrderDetails($detalle, $order->id, $order->BOD_BODEGA,  $rulesApproval);
        return [
            'pedido'                            => $order,
            'detalle'                           => data_get($enrichedDetails, 'details', []),
            'segmento'                          => $segmento,
            'pendiente_aprobacion_comercial'    => $this->approvalFuntions->validatePendingCommercialApproval($order),
        ];
    }



    protected function enrichOrderDetails(Collection $details, int $orderId, string $warehouse, array $reglasAprobacion)
    {
        $hasPending = false;
        $hasExisting = false;
        $itemIds = $details->pluck('id')->toArray();
        $codesList = $details->reject(function ($item) {
            return $item->ART_TIPO_COMPRA ===  'L';
        })->pluck('ART_CODIGO');
        $groupsByAbc = $this->orderAndQuotationService->getApiSegmentsAbcItem($codesList);
        $stockByItem = $this->orderRepository->getStockByItemAndWarehouse($itemIds, $warehouse)
            ->keyBy('detalle_id'); // mapeo por detalle_id para acceso rapido
        $details->transform(function ($item) use ($orderId, &$hasPending, &$hasExisting, $stockByItem, $groupsByAbc, $reglasAprobacion) {
            $proveedores        = $this->functionsService->getItemProviders($orderId, $item, $hasPending, $hasExisting);
            $item->proveedores  = $proveedores['proveedores'];
            $hasPending         = $proveedores['pendientes'] ?: $hasPending;
            $hasExisting        = $proveedores['existentes'] ?: $hasExisting;
            // Obtener stock (desde el map precargado)
            $stock              = $stockByItem->get($item->id);
            if ($stock && $stock->seleccionado > 0 && $item->ART_TIPO_COMPRA != 'L') {
                $hasExisting = true;
            }
            $item->priceChange      = round($this->orderAndQuotationService->calculatePriceVariationPercentage($item->ART_VALOR_ORIGINAL, $item->ART_VALOR), 3);
            $item->discountChange   = round($this->orderAndQuotationService->calculateDiscountChange($item->descuento_original, $item->descuento), 3);
            $item->reglasAprobacion = $reglasAprobacion;

            /** procesod de segmentacion abc  */
            $myAbc = $groupsByAbc->get($item->ART_CODIGO, collect());
            $classAbc = ($myAbc && $myAbc->isNotEmpty()) ? data_get($myAbc->first(), 'tuval', '-') : '-';
            $item->clasificacion_tuval = $classAbc;
            return $item;
        });

        return [
            'details'       => $details,
            'hasPending'    => $hasPending,
            'hasExisting'   => $hasExisting
        ];
    }


    protected function getPurchaseOrdersWithReceipts($orderNumber)
    {
        return $this->orderRepository->getPurchaseOrdersByOrderNumber($orderNumber)
            ->map(function ($orden) {
                $receipt = $this->purchaseReceiptService->setPurcharseOrderdetails($orden);
                return count($receipt->detalle) > 0 ? $receipt : null;
            })
            ->filter()
            ->values();
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

    protected function calculateOrderStatus($details, $transfers)
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


    protected function getPorcentilChangeDiscount(GpUsuario $user, GpPedido $order)
    {

        if (in_array($user->perfil_id, [1, 2])) {
            return [
                'regla_precio'    => ['min' => -100, 'max' => 100],
                'regla_descuento' => ['min' => -100, 'max' => 100],
            ];
        }
        $data = [
            'regla_precio'    => ['min' => 0, 'max' => 0],
            'regla_descuento' => ['min' => 0, 'max' => 0],
        ];
        $vendedorOrder = $order->usuario->vendedor ?? null;
        if (!$vendedorOrder) {
            return $data;
        }
        $zonasOrder      = $vendedorOrder->zonas->pluck('id')->toArray();
        $typeSellerOrder = $vendedorOrder->tipo_vendedor_id;

        if (empty($zonasOrder) || !$typeSellerOrder) {
            return $data;
        }

        if (in_array($user->perfil_id, [28])) {
            $reglas = GpReglaAprobacion::where('user_autorizador_id', $user->id)
                ->whereIn('zona_id', $zonasOrder)
                // Omitimos el tipo_vendedor_id porque el VP no evalúa por tipo de hijo
                ->whereIn('regla_negocio_id', [2, 3])
                ->select('regla_negocio_id', 'descuento_min', 'descuento_max')
                ->get();
        } else {
            $user->loadMissing(['vendedor.jerarquia.relaciones']);
            $headerAuth = $user->vendedor ?? null;

            if (!$headerAuth || !$headerAuth->jerarquia) {
                return $data;
            }

            $hijosDelJefe = $headerAuth->jerarquia->relaciones->pluck('tipo_vendedor_hijo_id')->toArray();

            // Si el creador de la cotización no es su subordinado, lo bloqueamos
            if (!in_array($typeSellerOrder, $hijosDelJefe)) {
                return $data;
            }

            $typeSellerJefe = $headerAuth->tipo_vendedor_id;
            $reglas = GpReglaAprobacion::where('user_autorizador_id', $user->id)
                ->whereIn('zona_id', $zonasOrder)
                ->where('tipo_vendedor_id', $typeSellerJefe) // Filtro estricto de jerarquía
                ->whereIn('regla_negocio_id', [2, 3])
                ->select('regla_negocio_id', 'descuento_min', 'descuento_max')
                ->get();
        }

        foreach ($reglas as $regla) {
            $key = $regla->regla_negocio_id == 3 ? 'regla_precio' : 'regla_descuento';
            $data[$key]['min'] = round((float)$regla->descuento_min, 2);
            $data[$key]['max'] = round((float)$regla->descuento_max, 2);
        }
        return $data;
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

    public function generateInvoiceOrder(int $orderId)
    {

        $order = $this->getOrderOrFail($orderId);
        if (!empty($order->factura) || $order->factura != '' || $order->factura != null) {
            throw new  \RuntimeException('El pedido ya fue facturado');
        }
        if ($order->origen === OrderOrigenEnum::FACTURA_DEMANDA->value) {
            $order->fe_facturacion_reserva = Carbon::now()->format('Y-m-d');
            $order->save();
        }
        $this->recalculateAndSendInvoice($order);
        return $order->refresh();
    }

    public function isInvoiceDateValidForReservedOrder(GpPedido $pedido): bool
    {
        if ($pedido->origen !== OrderOrigenEnum::FACTURA_DEMANDA->value) {
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


    public function getOrderOrFail(int $orderId)
    {
        $order = GpPedido::where('id', $orderId)->whereNotIn('estado', [
            OrderStatus::RECHAZADO,
            OrderStatus::ANULADA,
            OrderStatus::ELIMINADO
        ])->first();
        if (!$order) {
            throw new  \RuntimeException('Pedido no encontrado o no cumple las condiciones para facturarse');
        }
        return $order;
    }



    protected function recalculateAndSendInvoice(GpPedido $order)
    {
        $this->functionsService->recalculateOrder($order->id);
        $this->invoiceService->sendInvoiceGp($order);
    }






    /**
     *   FUNCION PARA VOLVER A PEDIR UN PEDIDO
     */

    public function loadOrderForCopy(int $orderId, GpUsuario $user): array
    {
        $order = GpPedido::findOrFail($orderId);
        $cliente = $this->clientService->getAvailabeCreditCustomer($order->CLI_RUC);
        $clienteNew =  data_get($cliente, 'cliente');
        $detalles = $this->orderAndQuotationService->getDetailQuotationOrOrder($order, $clienteNew, 'pedido', false, false);
        $this->functionsService->recalculateIva($detalles);
        $detalles->transform(function ($item) {
            $item->imagenes = $this->orderAndQuotationService->getImageArticle($item->ART_CODIGO_TRES_LETRAS);
            $item->tipo = 'pedido';
            $item->detalle_id = $item->id;
            return $item;
        });
        $registroCopiaId        = $this->orderAndQuotationService->createRegisterCopy($order->id, $user, 'pedido', 'volver_a_pedir');
        $imagenesOperaciones    = $this->orderAndQuotationService->getAllImagensOperations($order->id, 'pedido');
        $documentosPdf          = $this->orderAndQuotationService->getFormattedPdfMedia($order);
        return [
            'pedido'                => $order,
            'cliente'               => $clienteNew,
            'items'                 => $detalles,
            'imagenesOperaciones'   => $imagenesOperaciones,
            'documentosPdf'         => $documentosPdf,
            'segmento'              => data_get($cliente, 'segmentos'),
            'registro_copia'        => $registroCopiaId,
            'mode'                  => QuotationMode::REORDER->value,
        ];
    }


    /*** funcion para copiar pedido y cotizacion */

    public function copyQuoteOrOrder(int $rowId, int  $sucursalId, int $clienteId, string $type, GpUsuario $user): array
    {
        $initialCLient      = GpCliente::find($clienteId);
        $cliente            =  $this->clientService->getAvailabeCreditCustomer($initialCLient->CLI_RUC);
        $data               = ($type === 'cotizacion') ? GpCotizacion::findOrFail($rowId) : GpPedido::findOrFail($rowId);
        $clienteNew         =  data_get($cliente, 'cliente');
        $detalles           = $this->orderAndQuotationService->getDetailQuotationOrOrder($data, $clienteNew, $type, true, true);
        $this->functionsService->recalculateIva($detalles);
        $registroCopiaId = $this->orderAndQuotationService->createRegisterCopy($data->id, $user, $type, 'copia');
        $detalles->transform(function ($item) use ($type) {
            $item->imagenes = $this->orderAndQuotationService->getImageArticle($item->ART_CODIGO_TRES_LETRAS);
            $item->tipo     = $type;
            return $item;
        });
        $imagenesOperaciones    = $this->orderAndQuotationService->getAllImagensOperations($data->id, $type);
        $documentosPdf          = $this->orderAndQuotationService->getFormattedPdfMedia($data);
        $modeEnum = QuotationMode::fromType($type);
        return [
            'cliente'               => $clienteNew,
            'segmento'              => data_get($cliente, 'segmentos'),
            'mode'                  => $modeEnum->value,
            'registro_copia'        => $registroCopiaId,
            'sucursal_id'           => $sucursalId,
            'items'                 => $detalles,
            'imagenesOperaciones'   => $imagenesOperaciones,
            'documentosPdf'         => $documentosPdf
        ];
    }


    public function buscarItemlTest($item)
    {
        return $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $excludeItemsL = null);
    }


    public function generateOrderDispatch(int $id)
    {
        $order = GpPedido::find($id);
        $this->invoiceService->generateDisptachOrder($order);
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
                if (filled($pedido->factura) && $pedido->estado === OrderStatus::FACTURADO) {
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
                    if ($pedido->estado === OrderStatus::FACTURADO && filled($pedido->factura)) {
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


    public function updateOrderAndRecalculate(int $orderId)
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

    protected function getDataQuotationToUpdateOrder(int $quotationId)
    {
        $data = GpCotizacion::with([
            'detallecotizacion.proveedores',
            'detallecotizacion.stock',
        ])->find($quotationId);
        return $data->toArray();
    }

    public function fillOrderHeader(GpPedido $order, array $dataQuotation)
    {

        $order->fill([
            'cotizacion_id'                 => $dataQuotation['id'],
            'porcentage_descuentooriginal'  => $dataQuotation['porcentage_descuentooriginal'],
            'porcentage_descuento'          => $dataQuotation['porcentage_descuento'],
            'subtotal'                      => number_format((float) $dataQuotation['subtotal'] + (float) $dataQuotation['descuento'], 2, '.', ''),
            'descuento'                     => number_format((float) $dataQuotation['descuento'], 2, '.', ''),
            'subtotalmenosdescuento'        => number_format((float) $dataQuotation['subtotal'], 2, '.', ''),
            'iva'                           => number_format((float) $dataQuotation['iva'], 2, '.', ''),
            'total'                         => number_format((float) $dataQuotation['total'], 2, '.', ''),
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

        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $netSubtotal = 0.0;
        $totalIva = 0.0;
        $orderId = data_get($data, 'id');


        $detailOrder = $this->orderRepository->getAllDetailsOrderById($orderId);
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

            $lineNet = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;

            $impuestoPorcentaje = (float) $impVtaByCodigo[$item->ART_CODIGO] ?? 0;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);

            // calculos monetarios
            $subtotal += $lineaSubtotal;
            $totalDiscount += $lineaDiscount;
            $netSubtotal += $lineNet;
            $totalIva += $lineaIva;

            $item->subtotal = $lineaSubtotal;
            $item->ART_IMP_VTA = $impuestoPorcentaje;
            $item->ART_IVA = $lineaIva;
            return $item;
        })
            ->filter()
            ->values();
        $data['subtotal'] = round($subtotal, 2);
        $data['descuento'] = round($totalDiscount, 2);
        $data['subtotalmenosdescuento'] = round($netSubtotal, 2);
        $data['iva'] = round($totalIva, 2);
        $data['total'] = round($netSubtotal + $totalIva, 2);


        $facturarExistente = [];
        $facturarExistente['pedido'] = $data;
        $facturarExistente['detalle'] = $processed;
        return $facturarExistente;
    }


    public function generateInvoiceFromExistingOrder($data, $detalle, $user)
    {
        //Bloqueo de permisos
        $parentOder = GpPedido::where('id', $data['id'])->lockForUpdate()->firstOrFail();
        $detailsCheck = GpPedidoDetalle::where('pedido_id', $parentOder->id)->lockForUpdate()->get()->keyBy('id');
        $stocksCheck = GpPedidoDetalleStock::where('pedido_id', $parentOder->id)->lockForUpdate()->get()->keyBy('id');
        //validacion de stock y detalle del pedido para nuevos pedidos
        foreach ($detalle as $item) {
            $itemBase = $detailsCheck->get($item['id']);
            if (!$itemBase) {
                abort(409, "El artículo con código {$item['ART_CODIGO']} ya fue procesado o eliminado por otro usuario. Por favor recarga la página.");
            }

            if ($itemBase->ART_CANTIDAD < $item['ART_CANTIDAD']) {
                abort(409, "El artículo con código {$item['ART_CODIGO']} ya fue procesado en otro pedido. Por favor recarga la página.");
            }

            if ($item['ART_TIPO_COMPRA'] != 'SERV' && $item['ART_TIPO_COMPRA'] != 'L') {
                $st = data_get($item, 'stocks');
                if (empty($st)) {
                    abort(409, "Error de validación de stock para {$item['ART_CODIGO']}. Recarga la página.");
                }

                $stockBase = $stocksCheck->get($st['id']);
                if (!$stockBase) {
                    abort(409, "El artículo con código {$item['ART_CODIGO']} ya fue procesado. Por favor recarga la página.");
                }

                if ($st['seleccionado'] > $stockBase->seleccionado) {
                    abort(409, "El artículo con código {$item['ART_CODIGO']} ya fue procesado en otro pedido. Por favor recarga la página.");
                }
            }
        }


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
                continue;
            }

            // // Si seleccionado == cantidad -> omitir (no eliminar la línea original)
            if ((float) $stock->seleccionado === (float) $articulo->ART_CANTIDAD) {
                $this->clearStockArticleOrderExistence($data['id'], $articulo->id);
                $articulo->delete();
                continue;
            }

            // si el seleccionado es mayor a 0 y si es menor que ART_CANTIDAD RESTA para actualizar el nuevo detalle
            if ((float)$stock->seleccionado > 0 && (float)$stock->seleccionado < (float)$articulo->ART_CANTIDAD) {
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
                $pendiente = max(0, (float) $prov->ingresado - (float) $prov->ejecutado);
                if ($pendiente <= 0) {
                    continue;
                }

                $toDeduct = min((float) $artL->ART_CANTIDAD, $pendiente);
                if ($toDeduct <= 0) {
                    break;
                }

                $artL->ART_CANTIDAD = (float) $artL->ART_CANTIDAD - $toDeduct;
            }

            if ($provList->isNotEmpty()) {
                if ($artL->ART_CANTIDAD <= 0) {
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
        $this->cloneMediaFromParent($parentOder, $newOrder);
        $this->updateHeaderOrderTransferVta($parentOder);
        DB::afterCommit(function () use ($newOrder) {
            $this->invoiceService->checkSendInvoice($newOrder, true);
        });
        Log::info('Pedido desde Facturar Existente Generado Correctamente en generateInvoiceFromExistingOrder', [
            'pedido' => $newOrder->pedido
        ]);
        return $newOrder;
    }


    protected function prepareToStoreOrderHeaderExistence($data, $user)
    {
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $newOrder = $this->orderBuilder->formPayloadInvoiceExistence($data, $user, $puntoVenta);
        $newOrder->save();
        return $newOrder;
    }

    protected function storeFillDetailOrderExistence($detailOrder, $newOrder)
    {

        foreach ($detailOrder as $i => $item) {
            $newOrderDetail = $this->orderBuilder->formPayloadOrderDetail($newOrder->id, $item, $i);
            $newOrderDetail->save();
            if ($item['ART_TIPO_COMPRA'] != 'SERV' && count($item['proveedores']) > 0) {
                $this->storeFillProviderDetailExistence($item['proveedores'], $newOrderDetail, $newOrder, $item);
            }

            if ($item['stocks']) {
                $this->storeDetailStockExistence($item['stocks'], $newOrder->id, $newOrderDetail->id);
            }
        }
    }

    protected function storeFillProviderDetailExistence($providers, $newOrderDetail, $newOrder, $oldDetailOrder)
    {
        foreach ($providers as $provider) {
            $newProv = $this->orderBuilder->formPayloadOrderDetailProvider($provider, $newOrder->id, $newOrderDetail->id, "yes");
            $newProv->save();
            if ($provider['orden_detalle']) {
                $this->storeOrUpdateOrderSalesProvider($provider['orden_detalle'], $oldDetailOrder, $provider);
            }
        }
    }


    protected function storeOrUpdateOrderSalesProvider($orderSales, $oldDetailOrder, $oldProvider)
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

            $delta = (float) ($oldDetailOrder['ART_CANTIDAD'] ?? 0);
            if ($delta <= 0) {
                break;
            }
            $prev = (float) ($oldProv->ejecutado ?? 0);
            $cap = (float) ($oldProv->CANTIDAD ?? 0);

            if ($cap <= 0 || $prev >= $cap) {
                break; // ya completo o sin cantidad base
            }
            $oldProv->ejecutado = min($cap, $prev + $delta);
            $oldProv->save();


            $ordCGD = GpOrdenDeCompraGenerado::where('orden_id', $ordenCompra->id)->first();
            if (!$ordCGD) {
                continue;
            }
            if (($oldProv->ejecutado + 0.00001) >= $cap) {
                $ordCGD->estado = 'recibido';
                $ordCGD->save();
            }
        }
    }


    protected function storeDetailStockExistence($stock, $newOrderId, $newDetailId)
    {

        $nwStock = new GpPedidoDetalleStock();
        $nwStock->fill(attributes: [
            'pedido_id'                     => $newOrderId,
            'detalle_id'                    => $newDetailId,
            'STO_ART_BODEGA'                => data_get($stock, 'STO_ART_BODEGA', ''),
            'STO_ART_STOCK_DISPONIBLE'      => data_get($stock, 'STO_ART_STOCK_DISPONIBLE', 0),
            'seleccionado'                  => data_get($stock, 'seleccionado', 0),
            'reservado'                     => data_get($stock, 'reservado', 0),
        ]);
        $nwStock->save();
    }

    protected function deleteArticlesForNewOrder($deleteItems)
    {
        $deleteItems['articulos'] = $deleteItems['articulos'] ?? [];
        if (!empty($deleteItems['articulos'])) {
            $this->deleteArticlesOldOrder($deleteItems['articulos']);
        }
    }

    protected function deleteArticlesOldOrder($articles)
    {
        $articles = $articles instanceof Collection ? $articles : collect($articles);
        $articles->filter()->each->delete();
    }

    protected function recalculateOldAndNewOrder($oldOrderId, $newOrderId)
    {
        $this->functionsService->recalculateOrder($oldOrderId);
        $this->functionsService->recalculateOrder($newOrderId);
    }

    protected function clearStockArticleOrderExistence($orderId, $detailId)
    {
        GpPedidoDetalleStock::where('detalle_id', $detailId)
            ->where('pedido_id', $orderId)->delete();
    }

    protected function cloneMediaFromParent(GpPedido $order, GpPedido $newOrder)
    {

        $parentImages = GpPedidoImagen::where('pedido_id', $order->id)->get();
        if ($parentImages->isNotEmpty()) {
            $imageUrls = $parentImages->map(fn($img) => asset('storage/' . $img->ruta))->toArray();
            $this->orderAndQuotationService->copyImagesFromUrls($newOrder, $imageUrls);
        }

        $parentPdfs = $order->getMedia('documentos_pdf');
        if ($parentPdfs->isNotEmpty()) {
            $pdfUrls = $parentPdfs->map(fn($media) => $media->getFullUrl())->toArray();
            $this->orderAndQuotationService->copyPdfsFromUrls($newOrder, $pdfUrls);
        }
    }

    protected function updateHeaderOrderTransferVta($parentOrder)
    {
        $currentDetailIds = GpPedidoDetalle::where('pedido_id', $parentOrder->id)->pluck('id');
        if ($currentDetailIds->isEmpty()) {
            $parentOrder->update(['transferencia_en_proceso' => false]);
            return;
        }
        $hasActiveTransfer = GpTrasladosVentas::whereIn('detalle_id', $currentDetailIds)
            ->where('picking', 1)
            ->exists();

        $parentOrder->update(['transferencia_en_proceso' => $hasActiveTransfer]);
    }


    /** actualizacion de orden de compra y nota de cliente */

    public function syncPurchaseOrderAndNoteByOrder(int $id, ?string $newOrd, ?string $newNote)
    {
        $order = GpPedido::findOrFail($id);
        $inputs = [
            'orden_de_compra' => $newOrd,
            'nota_cliente' => $newNote
        ];

        $updates = collect($inputs)
            ->map(fn($value) => trim((string) $value))
            ->filter(fn($value) => strlen($value) > 0)
            ->all();

        if (!empty($updates)) {
            $order->update($updates);
        }
        return $order;
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



    public function fetchOrderFromReservations($perPage = 10, $search, $sortBy = 'gp.created_at', $sortOrder = 'desc', $ptoVtaId): LengthAwarePaginator
    {
        $allowedSorts = [
            'gp.created_at',
            'gp.pedido',
            'gp.factura',
            'gp.estado',
            'gp.CLI_RUC',
            'gp.CLI_RAZON_SOCIAL',
            'ct.cotizacion',
        ];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'gp.created_at';
        }

        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = GpPedido::query()
            ->without(['detalle', 'sucursal', 'cliente', 'gpcotizacion'])
            ->from('gp_pedidos as gp')
            ->select([
                'gp.id',
                'gp.pedido',
                'ct.id as cotizacion_id',
                'ct.PTO_VTA_DESCRIPCION AS pto_vta',
                'ct.cotizacion as nro_cotizacion',
                'gp.factura',
                'gp.estado',
                'gp.CLI_RUC as ruc_cliente',
                'gp.CLI_RAZON_SOCIAL as razon_social_cliente',
                'gp.USERID',
                'gp.COND_ID',
                'gp.created_at',
                'gp.total',
                'gp.origen',
                'gp.fe_facturacion_reserva',
                'gp.pendiente_salida',
                'gp.factura_siguiente_mes',
                'gp.requiere_aprobacion_comercial',
                'gp.aprobado_comercial',
                'gp.requiere_aprobacion_crediticia',
                'gp.aprobado_crediticio',
                'gp.factura_a_demanda',
            ])
            ->join('gp_cotizacions as ct', 'ct.id', '=', 'gp.cotizacion_id')
            ->where('gp.origen', OrderOrigenEnum::FACTURA_DEMANDA->value)
            ->whereNotIn('gp.estado', [
                OrderStatus::ELIMINADO,
                OrderStatus::RECHAZADO,
                OrderStatus::ANULADA,
                OrderStatus::FACTURADO
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $columns = [
                    'ct.cotizacion',
                    'ct.PTO_VTA_ID',
                    'gp.CLI_RUC',
                    'gp.CLI_RAZON_SOCIAL',
                    'gp.pedido',
                ];
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }



    /** funcion para el bloqueo de despacho de pedidos */


    public function updateOrderAndBlockDispach($id, $pendingDispach, GpUsuario $user)
    {
        $order                      = GpPedido::find($id);
        $order->pendiente_salida    = $pendingDispach;
        $order->user_block_id       = $user->id;
        $order->fecha_bloqueo       = Carbon::now()->format('Y-m-d H:i:s');
        $order->save();
        return $order;
    }



    /*** funcion para validar la existencia de guias de un pedido  */

    public function hasDeliveryNoteAjusted($id): bool
    {
        $order = GpPedido::find($id);
        if (!$order) {
            abort(404, "El pedido con ID {$id} no existe.");
        }
        return GpGuiaRemisionDespacho::whereHas('guiaRemision', function ($q) use ($order) {
            $q->where('pedido', $order->pedido)
                ->where('tipo', 'despacho')
                ->whereNotIn('estado', ['pendiente', 'eliminada']);
        })->exists();
    }



    /** funciones de sendfacturas gp  */

    public function sendInvoicesToGp()
    {
        if ($this->orderAndQuotationService->isWeekend() || $this->orderAndQuotationService->isBussinessDay()) {
            Log::info('sendInvoicesToGp - No se enviarán facturas a GP porque es fin de semana o día no hábil.');
            return;
        }
        GpPedido::where('estado', OrderStatus::ESPERA)
            ->where('factura_siguiente_mes', 0)
            ->chunk(100, function ($orders) {
                foreach ($orders as $ord) {
                    $this->invoiceService->checkSendInvoice($ord);
                }
            });
        $ordersL = GpPedido::where('estado', OrderStatus::ORDEN_DE_COMPRA)->get();
        foreach ($ordersL as $ord) {
            if ($this->valdidateOrdersProviders($ord)) {
                $ord->estado = OrderStatus::ESPERA;
                $ord->save();
                $this->invoiceService->generateDisptachOrder($ord);
                $this->invoiceService->sendInvoiceGp($ord);
            }
        }
    }

    public function valdidateOrdersProviders(GpPedido $order)
    {
        $ordProv = GpPedidoDetalleProveedores::join('gp_orden_de_compra_generados', 'gp_orden_de_compra_generados.detalle_id', 'gp_pedido_detalle_proveedores.detalle_id')
            ->where('pedido_id', $order->id)
            ->select('gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO')
            ->get();

        if ($ordProv->isEmpty()) {
            return false;
        }

        foreach ($ordProv as $prov) {
            if ($prov->recibido == 'no') {
                return false;
            }

            $cadenaLimpia = trim($prov->ORDEN_COMPRA_RECIBO, '_');
            if (empty($cadenaLimpia)) {
                return false;
            }

            $listaRecibos = explode('_', $cadenaLimpia);
            foreach ($listaRecibos as $reciboIndividual) {
                if (empty($reciboIndividual))
                    continue;

                $params = [
                    'documento' => $reciboIndividual,
                    'tipo' => 1
                ];

                $respData = $this->apiGpValidateStatusReceptionItemL($params);
                if (data_get($respData, 'estado') !== 'PROCESADO') {
                    return false;
                }
            }
        }
        return true;
    }



    public function apiGpValidateStatusReceptionItemL($params)
    {
        $resp = $this->apiGpReceiptService->callValidateReceivedItemLApi($params);
        return data_get($resp, 'data', []);
    }

    public function createTransferVtaOrders(string $orderNumber)
    {
        $pedido = GpPedido::where('pedido', $orderNumber)->first();
        $this->invoiceService->checkTraslado($pedido);
        return $pedido;
    }
}
