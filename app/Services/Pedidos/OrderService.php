<?php

namespace App\Services\Pedidos;

use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpArticulo;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
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
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\File;
use App\Services\Api\ApiSalesService;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
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
        StockWarehouseServiceInterface $stockWarehouseService
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
            $item['compartimiento'] = $this->getCompartimento($item['ART_CODIGO'], $pedido['BOD_BODEGA']);
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

        $compartment = $this->apiGpService->getStockByWarehouseAndCompartment($params);
        if (isset($compartment) && count($compartment) > 0 && isset($compartment[0]['COMPARTIMIENTO'])) {
            $stockDisponible = array_map('floatval', array_column($compartment, 'STOCK_DISPONIBLE'));
            array_multisort($stockDisponible, SORT_DESC, $compartment);

            return $compartment[0]['COMPARTIMIENTO'];
        }
        return null;
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
        try {
            DB::beginTransaction();
            $this->ensureAllItemsHaveValidQuantity($order['cotizacion']);
            $newOrder = $this->prepareOrderStoreFill($order, $user);
            $this->prepareOrderDetailStoreFill($order['cotizacion'], $newOrder);
            $this->orderAndQuotationService->clearTempDataQuotation($order['sucursal_id'], $order['PTO_VTA_ID'], $order['cliente_id'], $user->id);
            if (isset($newOrder->cotizacion_id) && $newOrder->cotizacion_id != null && $newOrder->cotizacion_id  != '') {
                $this->updateValueInvoiceQuotation($newOrder->cotizacion_id);
            }
            $this->invoiceService->checkSendInvoice($newOrder);
            DB::commit();
            return $this->buildSuccessResponse("Pedido {$newOrder->pedido} guardado", 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in storeOrder (guardar pedido )', [
                'error' => $e,
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
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
        $newOrder = new GpPedido();
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $order['PTO_VTA_ID'])->first();
        // $approvalComercial = $this->approvalService->checkoutApprovalComercial($order, true);
        $approvalComercial = $this->approvalService->checkOutApprovalCommercial($order, 'pedido');
        $approvalCredit = $this->approvalService->checkoutApprovalCredit($order);

        $requieresApproval = $approvalCredit != 'no';
        $motivoCrediticio = $requieresApproval ? $approvalCredit : null;
        $requiereAprobacionCrediticia = $requieresApproval ? 'yes' : 'no';

        $newOrder->fill([
            'cotizacion_id' => $order['cotizacion_id'],
            'user_id' => $user->id,
            'sucursal_id' => $order['sucursal_id'],
            'sucursal_facturacion_id' => $order['sucursal_facturacion_id'],
            'cliente_id' => $order['cliente_id'],
            'factura_posterior_despacho' => $order['factura_posterior_despacho'],
            'factura_a_demanda' => $order['factura_a_demanda'],
            'factura_siguiente_mes' => $order['factura_siguiente_mes'],
            'impuesto' => env('IVA_IMPUESTO'),
            'BOD_BODEGA' => $order['bodega_facturacion'],
            'PTO_VTA_ID' => $order['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION' => $puntoVenta->PTO_VTA_DESCRIPCION,
            'USERID' => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO' => $order['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC' => $order['CLI_RUC'],
            'CLI_RAZON_SOCIAL' => $order['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI' => $order['CLI_CORREO_ELECT_SRI'],
            'COND_ID' => $order['COND_ID'],
            'COND_ID_ORIGINAL' => $order['COND_ID_ORIGINAL'],
            'VEND_ID' => $order['VEND_ID'],
            'VEND_NOMBRES' => $order['VEND_NOMBRES'],
            'VEND_APELLIDOS' => $order['VEND_APELLIDOS'],
            'despacho' => $order['despacho'],
            'porcentage_descuentooriginal' => $order['porcentage_descuentooriginal'],
            'porcentage_descuento' => $order['porcentage_descuento'],
            'subtotal' => number_format((float) $order['subtotal'] + (float) $order['descuento'], 2, '.', ''),
            'descuento' => number_format((float) $order['descuento'], 2, '.', ''),
            'subtotalmenosdescuento' => number_format((float) $order['subtotal'], 2, '.', ''),
            'iva' => number_format((float) $order['iva'], 2, '.', ''),
            'total' => number_format((float) $order['total'], 2, '.', ''),
            'requiere_aprobacion_comercial' => $approvalComercial['requiere'],
            'motivo_aprobacion_comercial' => $approvalComercial['motivo'],
            'aprobado_comercial' => 'no',
            'aprobador_comercial_id' => null,
            'requiere_aprobacion_crediticia' => $requiereAprobacionCrediticia,
            'aprobado_crediticio' => 'no',
            'aprobador_crediticio_id' => null,
            'motivo_crediticio' => $motivoCrediticio,
            'nota_interna' => $order['nota_interna'],
            'nota_cliente' => $order['nota_cliente'],
            'orden_de_compra' => $order['orden_de_compra'],
        ]);
        if ($order['despacho'] == 'Despacho en ruta') {
            $newOrder->fecha_entrega = $order['fecha_entrega'];
        }
        $newOrder->save();
        $newOrder->pedido = 'PED' . str_pad($newOrder->id, 8, "0", STR_PAD_LEFT);
        $newOrder->save();
        return $newOrder;
    }



    public function prepareOrderDetailStoreFill($detailOrder, $newOrder)
    {
        Log::info('Cantidad de items recibidos para cotización: ' . count($detailOrder) . '-' . 'pedido_id :' . $newOrder->id);
        $usedItemLExcluded = [];
        foreach ($detailOrder as $i => $item) {

            /** antes de almacenar el detalle del pedido, verificamos los items L
             *  si tienen stock y sino se asigna un item que este disponible
             * considerando la clase y el tipo de impuesto
             */
            $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
            $hasStock = $this->stockWarehouseService->checkArticuloDisponible($item);
            if ($isTypeL && !$hasStock) {
                $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item,  $usedItemLExcluded);
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
            $newOrderDetail = new GpPedidoDetalle([
                'pedido_id' => $newOrder->id,
                'orden' => $i + 1,
                'ART_CODIGO' => $item['ART_CODIGO'],
                'ART_DESCRIPCION' => $item['ART_DESCRIPCION'],
                'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
                'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
                'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
                'ART_DECIMAL_CANTIDAD' => (int)$item['ART_DECIMAL_CANTIDAD'],
                'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
                'ART_MARCA' => $item['ART_MARCA'],
                'ART_MATERIAL' => $item['ART_MATERIAL'],
                'ART_PESO' => $item['ART_PESO'],
                'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
                'ART_VALOR' => (float) $item['ART_VALOR'],
                'ART_VALOR_ORIGINAL' => (float)$item['ART_VALOR_ORIGINAL'],
                'ART_DESCUENTO' => (float)$item['ART_DESCUENTO'],
                'ART_IVA' => (float)$item['ART_IVA'],
                'ART_CANTIDAD' => (float)$item['ART_CANTIDAD'],
                'ART_STOCK' => (float)$item['ART_STOCK'],
                'ART_IMP_VTA' => (float)$item['ART_IMP_VTA'],
                'ART_COSTO_PROMEDIO' => (float)$item['ART_COSTO_PROMEDIO'],
                'descuento' => (float)$item['descuento'],
                'descuento_original' => (float)$item['descuento_original'],
                'modificador' => (float)$item['modificador'],
            ]);
            $newOrderDetail->save();
            if ($item['proveedores']) {
                $this->storeDetailProviders($item['proveedores'], $newOrderDetail, $newOrder);
            }
            if ($item['stock']) {
                $this->storeDetailStock($item['stock'], $newOrderDetail, $newOrder);
            }
        }
    }

    public function storeDetailProviders($providers, $detailOrder, $newOrder)
    {
        foreach ($providers as $provider) {
            $recibido = isset($provider['recibido']) ? $provider['recibido'] : 'no';
            GpPedidoDetalleProveedores::create([
                'pedido_id' => $newOrder->id,
                'detalle_id' => $detailOrder->id,
                'ID_PROVEEDOR' => $provider['ID_PROVEEDOR'],
                'DESCRIPCION_ARTICULO' => $provider['DESCRIPCION_ARTICULO'],
                'FECHA_ENTREGA' => $provider['FECHA_ENTREGA'],
                'CANTIDAD' => $provider['CANTIDAD'],
                'COSTO_UNITARIO' => $provider['COSTO_UNITARIO'],
                'IMPUESTO' => $provider['IMPUESTO'],
                'TOTAL' => $provider['TOTAL'],
                'TOTALCONIMPUESTO' => $provider['TOTALCONIMPUESTO'],
                'NOTA' => $provider['NOTA'],
                'recibido' => $recibido,
                'MARGEN' => $provider['MARGEN']
            ]);
        }
    }
    public function storeDetailStock($stock, $detailOrder, $newOrder)
    {
        $requiereAprobacion = $newOrder->requiere_aprobacion_crediticia === 'yes'
            || $newOrder->requiere_aprobacion_comercial === 'yes';

        foreach ($stock as $item) {
            /** aplicar validacion de stock y actualizar el stock disponible en el pedidodetalles+tock
             *  si no tiene stock almacenarl en un array y retornar como mensaje de error
             */
            $newFormatStock = [
                'STO_ART_STOCK_DISPONIBLE' => $item['STO_ART_STOCK_DISPONIBLE'],
            ];
            if ($item['seleccionado'] > 0) {
                $newFormatStock = $this->getAndValidateOrderDetailStock($detailOrder, $item, $newOrder->id);
                if (!$newFormatStock) {
                    throw new \Exception("No se pudo obtener el stock del artículo {$detailOrder['ART_CODIGO']} en la bodega {$item['STO_ART_BODEGA']}");
                }
                if ($newFormatStock['STO_ART_STOCK_DISPONIBLE'] < $item['seleccionado']) {
                    throw new \Exception("El stock disponible del artículo {$detailOrder['ART_CODIGO']} en la bodega {$item['STO_ART_BODEGA']} es insuficiente para la cantidad seleccionada.");
                }
            }
            GpPedidoDetalleStock::create([
                'pedido_id' => $newOrder->id,
                'detalle_id' => $detailOrder->id,
                'STO_ART_BODEGA' => $item['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $newFormatStock['STO_ART_STOCK_DISPONIBLE'], // me devuelve el stock disponile
                'seleccionado' => $item['seleccionado'],
                'reservado' => $requiereAprobacion ? 0 : $item['seleccionado'],
            ]);
        }
    }



    public function updateValueInvoiceQuotation($orderId)
    {
        GpCotizacion::where('id', $orderId)->update(['estado' => 'procesado', 'facturado' => 1]);
    }

    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId)
    {
        $params = [
            'articulo' => $detailOrder->ART_CODIGO,
            'bodega' => $stock['STO_ART_BODEGA'],
        ];
        $stocksResult = $this->apiGpService->getStockWarehouse($params);
        Log::info("Resultado de stock para aprobacion: " . json_encode($stocksResult));
        if (is_null($stocksResult) || !is_array($stocksResult)) {
            throw new \Exception("No se pudo obtener el stock de los articulos");
        }
        // $stockResponseFormated = $this->stockWarehouseService->formatResultStocks($stocksResult);

        $this->assignmentDisponibleStock($stock, $stocksResult[0], $detailOrder);
        $this->stockWarehouseService->formatResultStockStoreOrder($stock, $detailOrder, $orderId);
        return $stock;
    }


    public function assignmentDisponibleStock(&$stock, $stockResponseFormatted, $detailOrder)
    {
        $stock['STO_ART_STOCK_DISPONIBLE'] = $stockResponseFormatted['STOCK_DISPONIBLE'] ?? 0;
        $stock['STO_ART_ARTICULO'] = $detailOrder->ART_CODIGO;
    }

    /**
     * ✅ Obtiene el detalle de un pedido especifico.
     *  detallepedido
     *
     */

    public function detailOrderById($data)
    {
        // 🔍 1. Obtener detalles del pedido
        $detalle = $this->orderRepository->getAllDetailsOrderById($data['id']);

        // 🔍 2. Consultar el crédito del cliente
        //  $cupoDisponible = $webservices->cobranzas('ConsultaCupoDisponibleCliente', $parameters);
        $credito = $this->apiBillingService->consultCreditClient([
            'ruc' => $data['CLI_RUC'],
            'idCompania' => config('services.api_gp.company'),
        ]);
        $estadoCuenta = [
            'cupoDisponible' => $credito['cupos'][0] ?? []
        ];

        // 🔍 3. Procesar proveedores y stock
        $pendientes = false;
        $existentes = false;
        $detalle->each(function ($item) use ($data, &$pendientes, &$existentes) {
            $proveedores = $this->functionsService->getItemProviders($data['id'], $item, $pendientes, $existentes);
            $item->proveedores = $proveedores['proveedores'];
            $pendientes = $proveedores['pendientes'] ?: $pendientes;
            $existentes = $proveedores['existentes'] ?: $existentes;

            $stock = $this->orderRepository->getStockByItemAndWarehouse($item->id, $data['BOD_BODEGA']);
            if ($stock && $stock->seleccionado > 0 && $item->ART_TIPO_COMPRA != 'L') {
                $existentes = true;
            }
        });


        // 🔍 4. Obtener órdenes de compra optimizadas
        $ordenes = $this->orderRepository->getPurchaseOrdersByOrderNumber($data['pedido'])
            ->map(function ($orden) {
                $recibodecompra = $this->purchaseReceiptService->setPurcharseOrderdetails($orden);
                return count($recibodecompra->detalle) > 0 ? $recibodecompra : null;
            })
            ->filter()
            ->values();

        // 🔍 5. Procesar traslados
        $trasladosVentas = $this->orderRepository->getTransferOrdersBySale($data['id']);
        if ($trasladosVentas->isEmpty()) {
            $pendientes = true; // Si no hay traslados, marcamos como pendientes
            $trasladosVentas = collect(); // Inicializamos como colección vacía
            // $existentes = false; // No hay traslados existentes
        } else {
            $trasladosVentas->each(function ($trasladoVenta) use (&$pendientes, &$existentes) {
                $guias = $this->orderRepository->getShipmentGuidesByTransfer($trasladoVenta);
                $trasladoVenta->guias = $guias;
                $guias->each(function ($guia) use (&$pendientes, &$existentes) {
                    if ($guia->estado != 'recibida')
                        $pendientes = true;
                    else
                        $existentes = true;
                });
                if (!$trasladoVenta->completado)
                    $pendientes = true;
            });
        }

        // 🔍 6. Obtener despacho, guías y devoluciones
        $orderDespacho = $this->orderRepository->getDispatchOrder($data['id']);
        $guiaRemision = $this->orderRepository->getShipmentGuides($data['pedido']);
        $devoluciones = $this->orderRepository->getReturnsByOrder($data['id']);

        return response([
            'success' => true,
            'detalle' => $detalle,
            'estadodecuenta' => $estadoCuenta,
            'ordendecompra' => $ordenes,
            'traslados' => $trasladosVentas,
            'orden_despacho' => $orderDespacho,
            'guias_remision' => $guiaRemision,
            'devoluciones' => $devoluciones,
            'pendientes' => $pendientes,
            'existentes' => $existentes,
        ]);
    }


    public function checkOutValidateDispatchReInvoice($devolucion)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)
            ->where('despachado_parcial', 1)->exists();
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
            $this->recalculateAndSendInvoice($order);
            DB::commit();
            Log::info("Pedido {$order->pedido} facturado desde Facturar Pedido - generateInvoiceOrder - facturarpedido");
            return $this->buildSuccessResponse("Pedido {$order->pedido} facturado", 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
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
            DB::commit();
            return response([
                'success' => true,
                'cliente' => $cliente,
                'registro_copia' => $registerCopyId,
                'currentSucursal' => $data['sucursal_id'],
                'test' => 'volver_a_pedir'
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
            $registerCopyId = $this->orderAndQuotationService->createRegisterCopy($data['pedido']['id'], $user, $typeCopy,  $typeRegisterCopy);
            $this->functionsService->recalculateIva($detalle);
            $this->functionsService->setCartFormQuotation($detalle, $data['pedido'], $user);
            DB::commit();
            return response([
                'success' => true,
                'cliente' => $cliente,
                'registro_copia' => $registerCopyId,
                'currentSucursal' => $data['sucursal_id'],
                'test' => 'copiarpedido'
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
        try {
            DB::beginTransaction();
            $mes = $this->getPreviousMonth();

            if ($this->shouldAbortBilling($mes)) {
                DB::rollBack();
                Log::channel('cron_process')->warning('⚠️ No se ejecuta facturación: Aún no llega el mes :' . $mes);
                return;
            }
            $pedidos = $this->orderRepository->getOrdersToInvoiceNextMonth($mes);
            foreach ($pedidos as $pedido) {
                $this->invoiceService->checkSendInvoice($pedido, true, false);
                Log::channel('cron_process')->info("✅ Pedido {$pedido->id} facturado correctamente de generateNextMonthInvoice - orderService");
            }
            DB::commit();
            return $this->buildSuccessResponse("Pedidos facturados", 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('cron_process')->error("❌ Error facturando pedido: {$e->getMessage()}");
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function getPreviousMonth()
    {
        return date("m", strtotime("-1 month"));
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
                    $detail =  $this->prepareOrderDetailToCreate($newOrder, $item, $i);
                    $detail->save();
                } else {
                    //actualiza
                    $fields  =  $this->prepareOrderDetailToUpdate($newOrder, $item,  $detail, $i);
                    $detail->fill($fields);
                    $detail->save();
                }
                $this->syncDetailProvidersUpdateOrCreate($newOrder, $detail->id, $item['proveedores'] ?? []);
                $this->syncDetailStockUpdateOrCreate($newOrder, $detail,     $item['stock']      ?? []);
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
            $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item,  $usedItemLExcluded);
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
        return  new GpPedidoDetalle($this->mapDetailFielsToUpdateOrCreated($newOrder, $item, $index));
    }

    public function prepareOrderDetailToUpdate($newOrder, $item, $detail, $index)
    {
        Log::info('Detalle recibido para actualizacion: ' . $detail['ART_CODIGO'] . 'DE PEDIDO :' . 'pedido_id :' . $newOrder->id);
        $usedItemLExcluded = [];
        $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
        $hasStock = $this->stockWarehouseService->checkArticuloDisponible($item);
        if ($isTypeL && !$hasStock) {
            $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item,  $usedItemLExcluded);
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
        return   $this->mapDetailFielsToUpdateOrCreated($newOrder, $item, $index);
    }

    public function mapDetailFielsToUpdateOrCreated($newOrder, $item, $i)
    {
        return  [
            'pedido_id' => $newOrder->id,
            'orden' => $i + 1,
            'ART_CODIGO' => $item['ART_CODIGO'],
            'ART_DESCRIPCION' => $item['ART_DESCRIPCION'],
            'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD' => (int)$item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA' => $item['ART_MARCA'],
            'ART_MATERIAL' => $item['ART_MATERIAL'],
            'ART_PESO' => $item['ART_PESO'],
            'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
            'ART_VALOR' => (float) $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL' => (float)$item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO' => (float)$item['ART_DESCUENTO'],
            'ART_IVA' => (float)$item['ART_IVA'],
            'ART_CANTIDAD' => (float)$item['ART_CANTIDAD'],
            'ART_STOCK' => (float)$item['ART_STOCK'],
            'ART_IMP_VTA' => (float)$item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO' => (float)$item['ART_COSTO_PROMEDIO'],
            'descuento' => (float)$item['descuento'],
            'descuento_original' => (float)$item['descuento_original'],
            'modificador' => (float)$item['modificador'],
        ];
    }

    public function syncDetailProvidersUpdateOrCreate($newOrder, $detailOrderId, $providers)
    {
        $incoming = [];
        foreach ($providers as $prov) {
            $recibido = isset($prov['recibido']) ? $prov['recibido'] : 'no';
            $model =  GpPedidoDetalleProveedores::updateOrCreate(
                [
                    'pedido_id' => $newOrder->id,
                    'pedido_detalle_id' => $detailOrderId,
                    'ID_PROVEEDOR' => $prov['ID_PROVEEDOR']
                ],
                [
                    'DESCRIPCION_ARTICULO' => $prov['DESCRIPCION_ARTICULO'],
                    'FECHA_ENTREGA'        => $prov['FECHA_ENTREGA'],
                    'CANTIDAD'             => $prov['CANTIDAD'],
                    'COSTO_UNITARIO'       => $prov['COSTO_UNITARIO'],
                    'IMPUESTO'             => $prov['IMPUESTO'],
                    'TOTAL'                => $prov['TOTAL'],
                    'TOTALCONIMPUESTO'     => $prov['TOTALCONIMPUESTO'],
                    'NOTA'                 => $prov['NOTA'],
                    'recibido'             => $recibido,
                    'MARGEN'               => $prov['MARGEN'],
                ]
            );
            $incoming[] = $model->id;
        }

        GpPedidoDetalleProveedores::where('pedido_id', $newOrder->id)
            ->where('detalle_id', $detailOrderId)
            ->whereNotIn('id', $incoming)
            ->delete();
    }
    public function syncDetailStockUpdateOrCreate(GpPedido $newOrder,  GpPedidoDetalle $detailOrder, array $stock)
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
                'reservado' =>  $item['seleccionado'],
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
            $stocks =  $this->orderRepository->getStockOrderDetailForCheckExistence($data['BOD_BODEGA'], $detailOrder->pluck('id'));
            $impVtaByCodigo = GpArticulo::query()
                ->whereIn('ART_CODIGO', $detailOrder->pluck('ART_CODIGO'))
                ->pluck('ART_IMP_VTA', 'ART_CODIGO');

            $processed  = $detailOrder->map(function ($item) use ($data, $impVtaByCodigo, $stocks,  &$subtotal,  &$totalDiscount,  &$netSubtotal,  &$totalIva) {
                $stock = $stocks->get($item->id);
                $cantidad = 0;

                if ($stock && $item->ART_TIPO_COMPRA != 'L' && $item->ART_TIPO_COMPRA != 'SERV' && $item->ART_TIPO_COMPRA != 'SER') {
                    $cantidad = $stock->seleccionado;
                }
                if ($item->ART_TIPO_COMPRA == 'SER') {
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

                $item->stocks         = $stock;
                $item->proveedores    = $validProviders;

                //CALCULOS
                $valorUnitario = (float)$item->ART_VALOR;
                $item->ART_CANTIDAD = (float) $cantidad;
                $descuentoUnitario = (float) $item->ART_DESCUENTO;

                $lineaSubtotal = $valorUnitario * (float) $cantidad;
                $lineaDiscount = $descuentoUnitario * (float) $cantidad;
                $lineNet = round(($valorUnitario - $descuentoUnitario) * (float)$cantidad, 2);
                $impuestoPorcentaje   = $impVtaByCodigo[$item->ART_CODIGO] ?? 0;
                $lineaIva = round($lineNet * ($impuestoPorcentaje / 100), 3);

                // calculos monetarios
                $subtotal += $lineaSubtotal;
                $totalDiscount += $lineaDiscount;
                $netSubtotal += $lineNet;
                $totalIva += $lineaIva;

                $item->subtotal       = $lineaSubtotal;
                $item->ART_IMP_VTA    = $impuestoPorcentaje;
                $item->ART_IVA        = round($lineaIva, 2);
                return $item;
            })
                ->filter()
                ->values();
            $total                          = $netSubtotal + $totalIva;
            $data['subtotal']               = round($subtotal, 2);
            $data['descuento']              = round($totalDiscount, 2);
            $data['subtotalmenosdescuento'] = round($netSubtotal, 2);
            $data['iva']                    = round($totalIva, 2);

            $data['total']                  = round($total, 2);


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
}
