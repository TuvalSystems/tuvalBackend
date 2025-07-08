<?php

namespace App\Services\Pedidos;

use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Services\Bodegas\Contracts\PurchaseReceiptServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Carbon\Carbon;
use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpVendedor;
use App\Models\GpPuntoVenta;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
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
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        ApiSalesService $apiSalesService,
        ApiGpService $apiGpService,
        ApprovalServiceInterface $approvalService,
        OrderRepositoryInterface $orderRepository,
        ApiBillingService $apiBillingService,
        FunctionsServiceInterface $functionsService,
        PurchaseReceiptServiceInterface $purchaseReceiptService,
        InvoiceServiceInterface $invoiceService
    ) {
        $this->apiGpService = $apiGpService;
        $this->apiSalesService = $apiSalesService;
        $this->approvalService = $approvalService;
        $this->orderRepository = $orderRepository;
        $this->apiBillingService = $apiBillingService;
        $this->functionsService = $functionsService;
        $this->purchaseReceiptService = $purchaseReceiptService;
        $this->invoiceService = $invoiceService;
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
        $ptoVenta = GpPuntoVenta::find($pedido['PTO_VTA_ID'])?->toArray();
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



    // PROCESO DE GUARDADO DE PEDIDOS

    public function storeOrder($order)
    {
        $this->ensureAllItemsHaveValidQuantity($order['cotizacion']);
        return $order;
    }


    public function ensureAllItemsHaveValidQuantity($order)
    {
        foreach ($order['cotizacion'] as $item) {
            if (empty($item['ART_CANTIDAD']) || $item['ART_CANTIDAD'] <= 0) {
                throw new \InvalidArgumentException("La cantidad del artículo '{$item['ART_CODIGO']}' debe ser mayor a cero.");
            }
        }
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
            $proveedores = $this->functionsService->getItemProviders($data, $item, $pendientes, $existentes);
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


    /** RECUPERAR PEDIDOS Y COPIAR PEDIDOS */

    public function restoreOrders($articulos, $bodegas)
    {
        $formatStock = [];
        foreach ($articulos as $value) {
            $stockRequests = array_map(fn($whr) => ['articulo' => $value['articulo'], 'bodega' => $whr,], $bodegas);
            $stockResults = array_map(fn($params) => $this->apiGpService->getStockWarehouse($params), $stockRequests);
            $formatted = $this->formatResultStocksOrders($stockResults);
            $formatStock = array_merge($formatStock, $formatted);
        }

        return $formatStock;

    }


    public function formatResultStocksOrders($stockResults)
    {
        if (empty($stockResults)) {
            return [];
        }
        $formattedStocks = [];
        foreach ($stockResults as $value) {
            if (!is_array($value)) {
                continue;
            }
            $formattedStocks = array_merge(
                $formattedStocks,
                array_map(
                    fn($item) => [
                        'STO_ART_ARTICULO' => $item['ARTICULO'] ?? '',
                        'STO_ART_BODEGA' => $item['BODEGA'] ?? '',
                        'STO_ART_STOCK_DISPONIBLE' => number_format((float) $item['STOCK_DISPONIBLE'], 2, '.', '') ?? 0,
                    ],
                    array_values($value)
                )
            );
        }
        return $formattedStocks;
    }





    /** funciones para el facturar siguiente mes */

    public function generateNextMonthInvoice()
    {
        try {
            DB::beginTransaction();
            $mes = $this->getPreviousMonth();

            if ($this->shouldAbortBilling($mes)) {
                return $this->buildErrorResponse("Aún no llega el mes", 500);
            }

            $pedidos = $this->orderRepository->getOrdersToInvoiceNextMonth($mes);

            foreach ($pedidos as $pd) {
                $this->invoiceService->checkSendInvoice($pd, true, false);
            }
            DB::commit();
            return $this->buildSuccessResponse("Pedidos facturados", 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->buildErrorResponse($th->getMessage(), 500);
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

    /** facturar pedido  */

    public function generateInvoiceOrder($orderId)
    {
        try {
            DB::beginTransaction();
            $order = $this->getOrderOrFail($orderId);
            $this->recalculateAndSendInvoice($order);
            DB::commit();
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
        $this->functionsService->recalculateOrder($order);
        $this->invoiceService->sendInvoiceGp($order);
    }

}
