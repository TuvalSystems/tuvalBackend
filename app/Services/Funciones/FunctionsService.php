<?php

namespace App\Services\Funciones;

use App\Models\GpArticulo;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class FunctionsService implements FunctionsServiceInterface
{

    protected $orderRepository;
    protected $apiGpService;
    protected $stockWarehouseService;

    public function __construct(OrderRepositoryInterface $orderRepository, ApiGpService $apiGpService, StockWarehouseServiceInterface $stockWarehouseService)
    {
        $this->orderRepository = $orderRepository;
        $this->apiGpService = $apiGpService;
        $this->stockWarehouseService = $stockWarehouseService;
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
        $articulosCodigo = $detalle->pluck('ART_CODIGO')->unique();
        $impuesto = GpArticulo::whereIn('ART_CODIGO', $articulosCodigo)->pluck('ART_IMP_VTA', 'ART_CODIGO');

        foreach ($detalle as $item) {
            if ($impuesto->has($item->ART_CODIGO)) {
                $item->ART_IMP_VTA = $impuesto[$item->ART_CODIGO];
                $item->save();
            }
        }
    }
}
