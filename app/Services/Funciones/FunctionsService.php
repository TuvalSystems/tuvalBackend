<?php

namespace App\Services\Funciones;

use App\Models\GpCliente;
use App\Models\GpArticulo;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacionDetalle;
use App\Models\GpVendedor;
use App\Models\GpPedidoDetalle;
use App\Services\Api\ApiGpService;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\GpTempCart;
use App\Models\GpTempCartProveedores;
use App\Models\GpTempCartStock;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class FunctionsService implements FunctionsServiceInterface
{

    protected $orderRepository;
    protected $apiGpService;
    protected $stockWarehouseService;
    protected $apiBillingService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ApiGpService $apiGpService,
        StockWarehouseServiceInterface $stockWarehouseService,
        ApiBillingService $apiBillingService,
    ) {
        $this->orderRepository = $orderRepository;
        $this->apiGpService = $apiGpService;
        $this->stockWarehouseService = $stockWarehouseService;
        $this->apiBillingService = $apiBillingService;
    }

    public function getItemProviders($data, $item, $pendientes = false, $existentes = false): array
    {
        $providers = $this->orderRepository->getSuppliersByOrderAndItem($data['id'], $item->id);
        if ($item->ART_DECIMAL_CANTIDAD == 0) {
            $item->ART_STOCK = (int) $item->ART_STOCK;
            $item->ART_CANTIDAD = (int) $item->ART_CANTIDAD;
        }

        // Procesar cada proveedor
        $providers->each(function ($provider) use (&$pendientes, &$existentes, $item) {
            $provider->ingresado ? $existentes = true : $pendientes = true;

            // Obtener las órdenes de compra relacionadas
            $orderDetails = $this->orderRepository->getPurchaseOrdersBySupplier($provider->id);

            // Calcular el margen
            $margin = ($provider->COSTO_UNITARIO / (1 - ($provider->MARGEN / 100))) / (1 - $item->descuento / 100);
            $provider->margen = number_format($margin, 4, '.', '');

            // Conversión a enteros si no tiene decimales
            if ($item->ART_DECIMAL_CANTIDAD == 0) {
                $provider->CANTIDAD = (int) $provider->CANTIDAD;
                $provider->ingresado = (int) $provider->ingresado;
            }

            // Asignar el detalle de órdenes de compra
            $provider->orden_detalle = $orderDetails;
        });
        return [
            'proveedores' => $providers,
            'pendientes' => $pendientes,
            'existentes' => $existentes,
        ];
    }



    /** para aprobacion crediticia de pedidos y para validar stock antes de guardar pedido en InventoryQueryService */

    public function consultStockDetail($data, $detalle, $pedidoId = null)
    {
        $arBodegas = [];
        $arArticulos = [];

        foreach ($detalle as &$linea) {
            if ($linea['ART_TIPO_COMPRA'] == 'L') {
                continue;
            }
            if (!isset($linea['stock'])) {
                $stock = GpPedidoDetalleStock::where('detalle_id', $linea['id'])->get()->toArray();
                $linea['stock'] = $stock;
            }

            foreach ($linea['stock'] as $articulo) {
                if ($articulo['seleccionado'] > 0) {
                    $arArticulos[] = array(
                        'articulo' => $linea['ART_CODIGO']
                    );
                    break;
                }
            }
        }

        if (count($arArticulos) > 0) {
            $arBodegas = $this->getBodegas($data, 1);
            $stockGeneral = $this->consultStockWarehouse($arArticulos, $arBodegas);
            $stockGeneral = collect($stockGeneral)->groupBy('STO_ART_ARTICULO') // Agrupamos primero por artículo
                ->map(function ($items) {
                    return $items->keyBy('STO_ART_BODEGA'); // Agrupamos dentro por bodega
                })
                ->toArray();
        }
        $alert = null;

        foreach ($detalle as &$articulo) {
            if (in_array($articulo['ART_TIPO_COMPRA'], ['L', 'SERV'])) {
                continue;
            }
            $articuloCodigo = $articulo['ART_CODIGO'];
            $articuloCantidad = $articulo['ART_CANTIDAD'];
            $isDecimal = $articulo['ART_DECIMAL_CANTIDAD'] != 0;
            $stockTotal = 0;

            if (!isset($stockGeneral[$articuloCodigo])) {
                $articulo['stock'] = [];
                continue;
            }

            foreach ($stockGeneral[$articuloCodigo] as $key => $st) {
                $disponible = $isDecimal ? number_format((float) $st['STO_ART_STOCK_DISPONIBLE'], 2, '.', '') : (int) $st['STO_ART_STOCK_DISPONIBLE'];
                $reservado = $this->stockWarehouseService->stockReservado($articuloCodigo, $st['STO_ART_BODEGA'], $pedidoId);
                $ajustado = max(0, $disponible - $reservado);
                $stockGeneral[$articuloCodigo][$key]['STO_ART_STOCK_DISPONIBLE'] = $ajustado;
                $stockTotal += $ajustado;
            }
            $articulo['stock'] = array_values($stockGeneral[$articuloCodigo]);

            if (($stockTotal - $articuloCantidad) < 0) {
                $alert .= "El articulo " . $articulo['ART_CODIGO'] . " tiene stock insuficiente. Stock disponible: " . $stockTotal . " - Cantidad pedido: " . $articulo['ART_CANTIDAD'] . "<br>";
            }
        }

        return $alert;
    }



    public function getBodegas($data, $consulta = null)
    {
        $query = GpRelacionPuntoVentaBodegaRelacionada::where('PTO_VTA_BOD_REL_PUNTO_VENTA', $data['PTO_VTA_ID']);
        if ($consulta) {
            $query->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA');
        }
        return $query->orderBy('orden', 'ASC')->pluck('PTO_VTA_BOD_REL_BODEGA', 'PTO_VTA_BOD_REL_BODEGA')->toArray();
    }

    public function consultStockWarehouse($articulos, $bodegas)
    {
        $formatStock = [];
        foreach ($articulos as $value) {
            $stockRequests = array_map(fn($whr) => ['articulo' => $value['articulo'], 'bodega' => $whr,], $bodegas);
            $stockResults = array_map(fn($params) => $this->apiGpService->getStockWarehouse($params), $stockRequests);
            $formatted = $this->stockWarehouseService->formatResultStocks($stockResults);
            $formatStock = array_merge($formatStock, $formatted);
        }
        return $formatStock;
    }


    /** funcion de recalcular pedido  */
    public function recalculateOrder($order)
    {
        $detalle = GpPedidoDetalle::where('pedido_id', $order->id)->get();
        if ($detalle->isEmpty()) {
            return;
        }
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', $detalle->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');

        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $netSubtotal = 0.0;
        $totalIva = 0.0;

        foreach ($detalle as $item) {
            $valorUnitario = (float) $item->ART_VALOR;
            $cantidad = (float) $item->ART_CANTIDAD;
            $descuentoUnitario = (float) $item->ART_DESCUENTO;

            $lineaSubtotal = $valorUnitario * $cantidad;
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineNet = ($valorUnitario - $descuentoUnitario) * $cantidad;
            $impuestoPorcentaje = (float) $articulosImpuesto[$item->ART_CODIGO] ?? 0;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);

            $subtotal += $lineaSubtotal;
            $totalDiscount += $lineaDiscount;
            $netSubtotal += $lineNet;
            $totalIva += $lineaIva;

            $item->ART_IMP_VTA = $impuestoPorcentaje;
            $item->ART_IVA = round($lineaIva, 2);
            $item->save();
        }
        $total = $netSubtotal + $totalIva;

        $order->subtotal = round($subtotal, 2);
        $order->descuento = round($totalDiscount, 2);
        $order->subtotalmenosdescuento = round($netSubtotal, 2);
        $order->iva = round($totalIva, 2);
        $order->total = round($total, 2);
        $order->save();
    }

    /** funcion para recalcular iva */
    public function recalculateIva($detalle)
    {
        if ($detalle->isEmpty()) {
            return;
        }

        //obtiene todos los codigos de atriculos únicos
        $articulosCodigo = collect($detalle)->filter( function ($item): bool {
            return isset($item->ART_CODIGO);
        })->pluck('ART_CODIGO')->unique()->values();
        $impuesto = GpArticulo::whereIn('ART_CODIGO', $articulosCodigo)->pluck('ART_IMP_VTA', 'ART_CODIGO');

        foreach ($detalle as $item) {
            if ($impuesto->has($item->ART_CODIGO)) {
                GpCotizacionDetalle::where('id', $item->id)
                ->update(['ART_IMP_VTA' => $impuesto[$item->ART_CODIGO]]);
            }
        }
    }


    /** obtrener clientes getcliente - para recuperacion de cotizacion */

    public function getClient($data)
    {
        $cliente = GpCliente::find($data['cliente_id']);
        if (!$cliente) {
            throw new \Exception('Cliente no encontrado');
        }
        $vendedor = GpVendedor::where('VEND_ID', $cliente->CLI_VENDEDOR)->first();
        $cliente->vendedor = $vendedor;

        $sucursales = $this->getClientBranches($cliente->CLI_RUC);
        $cliente->sucursales = $sucursales;
        $cliente->sucursalesfacturacion = $sucursales;

        $cupoDisponible = $this->getClientCreditInfo($cliente->CLI_RUC);


        $cliente->suspendido = $cupoDisponible['SUSPENDIDO'] ?? 0;
        $cliente->cupo_disponible = $this->formatCurrency($cupoDisponible['CUPO_DISPONIBLE_GP'] ?? 0);
        $cliente->cupo_asignado = $this->formatCurrency($cupoDisponible['CUPO_ASIGNADO'] ?? 0);

        return $cliente;

    }


    public function getClientCreditInfo($ruc)
    {
        $response = $this->apiBillingService->consultCreditClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return reset($response['cupos'][0]);
    }

    public function getClientBranches($ruc)
    {
        return GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
            ->where('CLI_SUC_RUC', $ruc)
            ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
            ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
            ->get();
    }

    public function formatCurrency($value)
    {
        return number_format((float) $value, 2, '.', ',');
    }


    public function findAvailableArticleCodeL($artArrm, $class = 'VTA-BNS-L', $limit = 300)
    {
        $candidates = GpArticulo::where('ART_TIPO_COMPRA', 'L')
            ->where('ART_CLASE_ARTICULO', $class)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
        foreach ($candidates as $article) {
            if (in_array($article->ART_CODIGO, $artArrm, true)) {
                continue ;
            }

            if ($this->stockWarehouseService->checkArticuloDisponible($article)) {
                return $article->ART_CODIGO;
            }
        }
        return null;
    }


    /** function remplazo de stTempCartForm */

    public function setCartFormQuotation($quotationDetails, $data, $user)
    {
        $this->clearExistCart($data['sucursal_id'], $data['cliente_id'], $data['PTO_VTA_ID']);
        foreach ($quotationDetails as $i => $item) {
            $cart = $this->storeCartItem($item, $data, $user, $i + 1);
            $this->storeCartProviders($cart->id, $item->proveedores ?? []);
            $this->storeCartStock($cart->id, $item->stock ?? []);
        }
    }

    public function clearExistCart($sucursal_id, $cliente_id, $PTO_VTA_ID)
    {
        GpTempCart::where('sucursal_id', $sucursal_id)
            ->where('puntodeventa_id', $PTO_VTA_ID)
            ->where('cliente_id', $cliente_id)
            ->delete();
    }

    public function storeCartItem($item, $data, $user, $index): GpTempCart
    {
        $cart = new GpTempCart();
        $cart->detalle_id = $item->id;
        $cart->sucursal_id = $data['sucursal_id'];
        $cart->cliente_id = $data['cliente_id'];
        $cart->puntodeventa_id = $data['PTO_VTA_ID'];
        $cart->user_id = $user->id;
        $cart->USERID = $user->USERID;
        $cart->ART_CODIGO = $item->ART_CODIGO;
        $cart->ART_DESCRIPCION = $item->ART_DESCRIPCION;
        $cart->ART_DESCRIPCION_ORIGINAL = $item->ART_DESCRIPCION_ORIGINAL;
        $cart->ART_CODIGO_TRES_LETRAS = $item->ART_CODIGO_TRES_LETRAS;
        $cart->ART_DECIMAL_CANTIDAD = $item->ART_DECIMAL_CANTIDAD;
        $cart->ART_PLAN_UMEDIDA = $item->ART_PLAN_UMEDIDA;
        $cart->ART_MARCA = $item->ART_MARCA;
        $cart->ART_MATERIAL = $item->ART_MATERIAL;
        $cart->ART_PESO = $item->ART_PESO;
        $cart->ART_TIPO_COMPRA = $item->ART_TIPO_COMPRA;
        $cart->ART_VALOR = $item->ART_VALOR;
        $cart->ART_IVA = $item->ART_IVA;
        $cart->ART_VALOR_ORIGINAL = $item->ART_VALOR_ORIGINAL;
        $cart->ART_DESCUENTO = $item->ART_DESCUENTO;
        $cart->ART_CANTIDAD = $item->ART_CANTIDAD;
        $cart->ART_STOCK = $item->ART_STOCK;
        $cart->ART_IMP_VTA = $item->ART_IMP_VTA;
        $cart->ART_COSTO_PROMEDIO = $item->ART_COSTO_PROMEDIO;
        $cart->descuento = $item->descuento;
        $cart->descuento_original = $item->descuento_original;
        $cart->modificador = $item->modificador;
        $cart->orden = $index;
        $cart->save();

        return $cart;
    }


    public function storeCartProviders($cartId, $providers)
    {
        foreach ($providers as  $prov) {
            GpTempCartProveedores::create([
                'id_tempcart' => $cartId,
                'ID_PROVEEDOR' => $prov->ID_PROVEEDOR,
                'DESCRIPCION_ARTICULO' => $prov->DESCRIPCION_ARTICULO,
                'FECHA_ENTREGA' => $prov->FECHA_ENTREGA,
                'CANTIDAD' => $prov->CANTIDAD,
                'COSTO_UNITARIO' => $prov->COSTO_UNITARIO,
                'IMPUESTO' => $prov->IMPUESTO,
                'TOTAL' => $prov->TOTAL,
                'TOTALCONIMPUESTO' => $prov->TOTALCONIMPUESTO,
                'NOTA' => $prov->NOTA,
                'MARGEN' => $prov->MARGEN
            ]);
        }
    }

    public function storeCartStock($cartId, $stock)
    {
        foreach ($stock as $stockData) {
            $selected = $stockData['seleccionado'] ?? 0;
            if (isset($stockData['seleccionado']) && $selected > $stockData['STO_ART_STOCK_DISPONIBLE']) {
                 $selected = $stockData['STO_ART_STOCK_DISPONIBLE'];
            }

            GpTempCartStock::create([
                'id_tempcart' => $cartId,
                'STO_ART_BODEGA' => $stockData['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $stockData['STO_ART_STOCK_DISPONIBLE'],
                'seleccionado' => $selected

            ]);
        }
    }
}
