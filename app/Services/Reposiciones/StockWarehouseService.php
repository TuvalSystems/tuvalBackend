<?php

namespace App\Services\Reposiciones;

use Carbon\Carbon;
use App\Models\GpArticulo;
use App\Models\GpTempCart;
use App\Models\GpDatosItemsL;
use App\Models\GpPedidoDetalle;
use Illuminate\Support\Facades\DB;
use App\Models\GpPedidoDetalleStock;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\Api\ApiGpService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Arr;

class StockWarehouseService implements StockWarehouseServiceInterface
{

    public function __construct(protected readonly ApiGpService $apiGpService)
    {
    }

    public function computeAvailableStockByWarehouseForArticle(array $stockResults, array $articulo)
    {
        $articulo['disponible'] = $this->checkArticuloDisponible($articulo);
        $articulo['stock'] = [];
        $articulo['ART_STOCK'] = 0;
        if (empty($stockResults) || empty($articulo['ART_CODIGO'])) {
            return $articulo;
        }
        $filtered = array_filter($stockResults, function ($item) use ($articulo) {
            return isset($item['STO_ART_STOCK_DISPONIBLE'], $item['STO_ART_BODEGA'], $item['STO_ART_ARTICULO']) &&
                $item['STO_ART_ARTICULO'] === $articulo['ART_CODIGO'];
        });

        if (!$filtered) {
            return $articulo;
        }

        // Bodegas involucradas (para agrupar y traer reservas en una sola pasada)
        $bodegas = array_values(array_unique(array_map(static fn($i) => (string) $i['STO_ART_BODEGA'], $filtered)));
        // Traer reservas por bodega en 2 consultas totales (pedidos + traslados)
        $reservas = $this->getReservedStockByWarehouses($articulo['ART_CODIGO'], $bodegas, null, false, null);

        $total = 0;
        $normalizados = [];
        foreach ($filtered as $item) {
            $bodega = (string) $item['STO_ART_BODEGA'];
            $stockBase = (float) $item['STO_ART_STOCK_DISPONIBLE'];
            $stockBase = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0
                ? (int) $stockBase
                : round($stockBase, 2);
            $resta = (float) ($reservas[$bodega] ?? 0);
            $disponible = max(0, $stockBase - $resta);
            $disponible = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0
                ? (int) $disponible
                : round($disponible, 2);

            $normalizados[] = [
                'STO_ART_BODEGA' => $bodega,
                'STO_ART_STOCK_DISPONIBLE' => $disponible,
            ];

            $total += $disponible;
        }
        $articulo['stock'] = $normalizados;
        $articulo['ART_STOCK'] = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0 ? (int) $total : round((float) $total, 2);
        return $articulo;
    }

    public function computeAvailableStockForArticle(array $stockResults, array $articulo, ?int $quotationId = null)
    {
        $articulo['disponible'] = $this->checkArticuloDisponible($articulo);
        $articulo['stock'] = [];
        $articulo['ART_STOCK'] = 0;
        if (empty($stockResults) || empty($articulo['ART_CODIGO'])) {
            return $articulo;
        }
        $filtered = array_filter($stockResults, function ($item) use ($articulo) {
            return isset($item['STO_ART_STOCK_DISPONIBLE'], $item['STO_ART_BODEGA'], $item['STO_ART_ARTICULO']) &&
                $item['STO_ART_ARTICULO'] === $articulo['ART_CODIGO'];
        });

        if (!$filtered) {
            return $articulo;
        }

        // Bodegas involucradas (para agrupar y traer reservas en una sola pasada)
        $bodegas = array_values(array_unique(array_map(static fn($i) => (string) $i['STO_ART_BODEGA'], $filtered)));
        // Traer reservas por bodega en 2 consultas totales (pedidos + traslados)
        $reservas = $this->getReservedStockByWarehouses($articulo['ART_CODIGO'], $bodegas, null, false, $quotationId);

        $total = 0;
        $normalizados = [];
        foreach ($filtered as $item) {
            $bodega = (string) $item['STO_ART_BODEGA'];
            $stockBase = (float) $item['STO_ART_STOCK_DISPONIBLE'];
            $stockBase = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0
                ? (int) $stockBase
                : round($stockBase, 2);
            $resta = (float) ($reservas[$bodega] ?? 0);
            $disponible = max(0, $stockBase - $resta);
            $disponible = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0
                ? (int) $disponible
                : round($disponible, 2);

            $normalizados[] = [
                'DESCRIPCION_ARTICULO' => (string) $item['DESCRIPCION_ARTICULO'],
                'STO_ART_ARTICULO' => (string) $item['STO_ART_ARTICULO'],
                'STO_ART_BODEGA' => $bodega,
                'STO_ART_STOCK_DISPONIBLE' => $disponible,
                'seleccionado' => $item['seleccionado']
            ];

            $total += $disponible;
        }
        $articulo['stock'] = $normalizados;
        $articulo['ART_STOCK'] = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0 ? (int) $total : round((float) $total, 2);
        return $articulo;
    }

    public function getReservedStockByWarehouses(string $articulo, array $bodegas, ?int $pedidoId = null, bool $forUpdate = false, ?int $excludeQuotationId = null): array
    {

        if (empty($bodegas))
            return [];
        $q = GpPedidoDetalleStock::query()
            ->join('gp_pedido_detalles', 'gp_pedido_detalle_stocks.detalle_id', '=', 'gp_pedido_detalles.id')
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalle_stocks.pedido_id')
            ->where('gp_pedido_detalles.ART_CODIGO', $articulo)
            ->whereIn('gp_pedido_detalle_stocks.STO_ART_BODEGA', $bodegas)
            ->where('gp_pedido_detalle_stocks.reservado', '>', 0)
            ->whereNotNull('gp_pedido_detalle_stocks.reservado')
            ->whereNotIn('gp_pedidos.estado', ['eliminado', 'rechazado', 'anulada'])
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado')
                    ->orWhere('gp_pedidos.despachado', 0);
            })
            // Comercial OK o no requiere
            ->where(function ($query) {
                $query->where('gp_pedidos.requiere_aprobacion_comercial', 'no')
                    ->orWhere(function ($q) {
                        $q->where('gp_pedidos.requiere_aprobacion_comercial', 'yes')
                            ->where('gp_pedidos.aprobado_comercial', 'yes');
                    });
            })
            // Crediticia OK o no requiere
            ->where(function ($query) {
                $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'no')
                    ->orWhere(function ($q) {
                        $q->where('gp_pedidos.requiere_aprobacion_crediticia', 'yes')
                            ->where('gp_pedidos.aprobado_crediticio', 'yes');
                    });
            });
        if ($pedidoId) {
            $q->where('gp_pedidos.id', '!=', $pedidoId);
        }

        if ($forUpdate) {
            $q->lockForUpdate();
        }

        $reservasPedidos = $q->select(
            'gp_pedido_detalle_stocks.STO_ART_BODEGA as bodega',
            DB::raw('SUM(gp_pedido_detalle_stocks.reservado) as total')
        )
            ->groupBy('gp_pedido_detalle_stocks.STO_ART_BODEGA')
            ->pluck('total', 'bodega')
            ->map(fn($v) => (float) ($v ?? 0))
            ->all();

        //sumar reservados de cotizaciones
        // 2) Traslados de reposición pendientes (agrupado por bodega_origen)
        $reservasTraslados = $this->getReservedStockByWarehousesForTransfer($articulo, $bodegas);

        // 4) Reservas pendientes de uso para cotizar
        $reservasPendientes = $this->getReservedStockByWarehousesPendingUse($articulo, $bodegas, $excludeQuotationId);
        // 5) Reservas ya cotizadas pero si uso
        // $reservasTrasladosCotizacion = $this->getReservedStockByWarehousesToPendingTransfer($articulo, $bodegas, $excludeQuotationId);
        $out = [];
        foreach ($bodegas as $b) {
            $out[$b] = (float) ($reservasPendientes[$b] ?? 0) + (float) ($reservasPedidos[$b] ?? 0) + (float) ($reservasTraslados[$b] ?? 0) + (float) ($reservasTrasladosCotizacion[$b] ?? 0);
        }
        return $out;
    }

    public function getReservedStockByWarehousesPendingUse($articulo, $bodegas, $excludeQuotationId = null)
    {
        if (empty($bodegas))
            return [];
        return DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', '=', 'st.detalle_id')
            ->join('gp_cotizacions as c', 'c.id', '=', 'd.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'c.id')
            ->whereIn('r.estado', ['aprobado', 'traslado'])
            // ->where('st.cantidad_traslado' ,'=', 0)
            ->where('d.ART_CODIGO', '=', (string) $articulo)
            ->whereIn('st.STO_ART_BODEGA', $bodegas)
            ->where('st.seleccionado', '>', 0)
            ->when($excludeQuotationId, function ($query) use ($excludeQuotationId) {
                $query->where('c.id', '!=', $excludeQuotationId);
            })
            ->select(
                'st.STO_ART_BODEGA as bodega',
                'c.cotizacion',
                DB::raw('SUM(st.seleccionado) as total')
            )
            ->groupBy('st.STO_ART_BODEGA', 'c.cotizacion')
            ->pluck('total', 'bodega')
            ->map(fn($v) => (float) ($v ?? 0))
            ->all();
    }

    public function getReservedStockByWarehousesToPendingTransfer($articulo, $bodegas, $excludeQuotationId = null)
    {
        if (empty($bodegas))
            return [];
        return DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', '=', 'st.detalle_id')
            ->join('gp_cotizacions as c', 'c.id', '=', 'd.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'c.id')
            ->whereIn('r.estado', ['traslado'])
            ->where('d.ART_CODIGO', '=', (string) $articulo)
            ->whereIn('st.STO_ART_BODEGA', $bodegas)
            ->where('st.cantidad_traslado', '>', 0)
            ->when($excludeQuotationId, function ($query) use ($excludeQuotationId) {
                $query->where('c.id', '!=', $excludeQuotationId);
            })
            ->select(
                'st.STO_ART_BODEGA as bodega',
                'c.cotizacion',
                DB::raw('SUM(st.cantidad_traslado) as total')
            )
            ->groupBy('st.STO_ART_BODEGA', 'c.cotizacion')
            ->pluck('total', 'bodega')
            ->map(fn($v) => (float) ($v ?? 0))
            ->all();
    }

    public function getReservedStockByWarehousesForTransfer($articulo, $bodegas)
    {
        return GpTrasladosReposicion::query()
            ->select('bodega_origen', DB::raw('SUM(COALESCE(cantidad,0) - COALESCE(trasladado,0)) as total'))
            ->whereIn('bodega_origen', $bodegas)
            ->where('ART_CODIGO', $articulo)
            ->where(function ($q) {
                $q->whereNull('completado')
                    ->orWhere('completado', 0);
            })
            ->groupBy('bodega_origen')
            ->pluck('total', 'bodega_origen')
            ->map(fn($v) => max(0, (float) ($v ?? 0)))
            ->all();
    }

    public function newFormatStock($stockResults, $articulo)
    {
        $articulo['disponible'] = $this->checkArticuloDisponible($articulo);
        $articulo['stock'] = [];
        $articulo['ART_STOCK'] = 0;

        $filteredStock = array_values(array_filter(
            $stockResults,
            fn($item) =>
            isset($item['STO_ART_STOCK_DISPONIBLE'], $item['STO_ART_BODEGA'], $item['STO_ART_ARTICULO']) &&
            $item['STO_ART_ARTICULO'] == $articulo['ART_CODIGO']
        ));

        if (empty($filteredStock)) {
            return $articulo;
        }
        $stockTotal = 0;
        foreach ($filteredStock as &$stockItem) {

            if (!isset($stockItem['STO_ART_STOCK_DISPONIBLE'], $stockItem['STO_ART_BODEGA'])) {
                continue;
            }
            // Formateo de cantidad según configuración de decimales
            $stockValue = $stockItem['STO_ART_STOCK_DISPONIBLE'];
            $stockItem['STO_ART_STOCK_DISPONIBLE'] = $articulo['ART_DECIMAL_CANTIDAD'] == 0
                ? (int) $stockValue
                : round((float) $stockValue, 2);

            // Cálculo de stock disponible (considerando reservas)
            $reservado = $this->stockReservado(
                $articulo['ART_CODIGO'],
                $stockItem['STO_ART_BODEGA']
            );

            $stockItem['STO_ART_STOCK_DISPONIBLE'] = max(0, $stockItem['STO_ART_STOCK_DISPONIBLE'] - $reservado);
            $stockTotal += $stockItem['STO_ART_STOCK_DISPONIBLE'];
        }

        $articulo['stock'] = $filteredStock;
        $articulo['ART_STOCK'] = $stockTotal;

        return $articulo;
    }
    public function stockReservado(string $articulo, string $bodega, ?int $pedidoId = null)
    {
        $reservado = 0;

        // Stock reservado en pedidos
        $query = GpPedidoDetalleStock::query()
            ->join('gp_pedido_detalles', 'gp_pedido_detalle_stocks.detalle_id', '=', 'gp_pedido_detalles.id')
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalle_stocks.pedido_id')
            ->where('gp_pedido_detalles.ART_CODIGO', $articulo)
            ->where('gp_pedido_detalle_stocks.STO_ART_BODEGA', $bodega)
            ->where('gp_pedido_detalle_stocks.reservado', '>', 0)
            ->whereNotNull('gp_pedido_detalle_stocks.reservado')
            ->whereNotIn('gp_pedidos.estado', ['eliminado', 'rechazado'])
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado')
                    ->orWhere('gp_pedidos.despachado', 0);
            })
            ->where(function ($query) {
                // Aprobación comercial
                $query->where('gp_pedidos.requiere_aprobacion_comercial', 'no')
                    ->orWhere(function ($q) {
                    $q->where('gp_pedidos.requiere_aprobacion_comercial', 'yes')
                        ->where('gp_pedidos.aprobado_comercial', 'yes');
                });
            })
            ->where(function ($query) {
                // Aprobación crediticia
                $query->where('gp_pedidos.requiere_aprobacion_crediticia', 'no')
                    ->orWhere(function ($q) {
                    $q->where('gp_pedidos.requiere_aprobacion_crediticia', 'yes')
                        ->where('gp_pedidos.aprobado_crediticio', 'yes');
                });
            });

        if ($pedidoId) {
            $query->where('gp_pedidos.id', '!=', $pedidoId);
        }

        $reservado += $query->sum('gp_pedido_detalle_stocks.reservado');

        // Stock en traslados de reposición
        $reservado += GpTrasladosReposicion::query()
            ->where('bodega_origen', $bodega)
            ->where('ART_CODIGO', $articulo)
            ->where(function ($q) {
                $q->whereNull('completado')
                    ->orWhere('completado', 0);
            })
            ->get()
            ->sum(fn($item) => ($item->cantidad ?? 0) - ($item->trasladado ?? 0));

        return $reservado;
    }

    public function checkArticuloDisponible($articulo)
    {
        // Artículos que no son tipo 'L' siempre están disponibles
        if ($articulo['ART_TIPO_COMPRA'] !== 'L') {
            return true;
        }
        // Verificar pedidos en estados específicos
        if ($this->hasActiveOrders($articulo['ART_CODIGO'])) {
            return false;
        }
        // Verificar pedidos facturados no despachados
        if ($this->hasPendingInvoicedOrders($articulo['ART_CODIGO'])) {
            return false;
        }
        // Verificar artículos de clase especial
        if (isset($articulo['ART_CLASE_ARTICULO'])) {
            if ($this->hasRecentDispatchedOrders($articulo['ART_CLASE_ARTICULO'], $articulo['ART_CODIGO'])) {
                return false;
            }
        } else {
            return false;
        }
        //Verificar si esta en carrito temporal
        if ($this->isTemporalCart($articulo)) {
            return false;
        }
        // si hay una devolución volver a colocar los dias maximos
        return true;
    }

    public function hasActiveOrders($articulo)
    {
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->whereIn('gp_pedidos.estado', ['espera', 'traslado', 'orden_de_compra'])
            ->where('gp_pedido_detalles.ART_CODIGO', $articulo)
            // ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->whereBetween('gp_pedidos.created_at', [
                Carbon::now()->subMonths(6)->startOfDay(),
                Carbon::now()->endOfDay()
            ])
            ->exists();
    }

    public function hasPendingInvoicedOrders($articulo)
    {
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where('gp_pedidos.estado', 'facturado')
            ->where('gp_pedido_detalles.ART_CODIGO', $articulo)
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado')
                    ->orWhere('gp_pedidos.despachado', 0);
            })
            // ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->whereBetween('gp_pedidos.created_at', [
                Carbon::now()->subMonths(6)->startOfDay(),
                Carbon::now()->endOfDay()
            ])
            ->exists();
    }

    public function isTemporalCart($articulo)
    {
        return GpTempCart::where('ART_CODIGO', $articulo['ART_CODIGO'])->exists();
    }

    public function hasRecentDispatchedOrders($artClaseArticulo, $artCodigo)
    {
        $datosL = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $artClaseArticulo)->first();
        if (!$datosL) {
            return false;
        }
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where('gp_pedidos.estado', 'facturado')
            ->where('gp_pedido_detalles.ART_CODIGO', $artCodigo)
            ->where('gp_pedidos.despachado', 1)
            // ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->whereDate('gp_pedidos.created_at', '>', now()->subDays($datosL['DATOS_ITEMS_L_DIA_MAXIMO'])->startOfDay())
            ->whereBetween('gp_pedidos.created_at', [
                Carbon::now()->subMonths(6)->startOfDay(),
                Carbon::now()->endOfDay()
            ])
            ->exists();
    }

    public function fetchStockWarehouseCompartiment(array $params)
    {
        $res = $this->apiGpService->getStockByWarehouseAndCompartment($params);

        // if (!data_get($res, 'success', false)) {
        //     $message = sprintf(
        //         '%s | Artículo: %s | Bodega: %s | Compartimento: %s',
        //         (string) data_get($res, 'message', 'Error'),
        //         Arr::get($params, 'articulo', 'N/D'),
        //         Arr::get($params, 'bodega', 'N/D'),
        //         Arr::get($params, 'compartimento', 'N/D')
        //     );
        //     throw new \RuntimeException($message, 500);
        // }

        $data = data_get($res, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }

    public function fetchStockBatchWarehouses(array $params)
    {
        $res = $this->apiGpService->getStockWarehouseAsync($params);

        if (!data_get($res, 'success', false)) {
            abort(502, 'No se pudo obtener el stock del artículo: ' . data_get($res, 'message', 'Error'));
            // throw new \RuntimeException("No se pudo obtener el stock del articulo".  data_get($res, 'message', 'Error'), 500);
        }

        $data = data_get($res, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }

    public function fetchStockByOnlyWarehouse(array $params)
    {
        $res = $this->apiGpService->getStockWarehouse($params);
        if (!data_get($res, 'success', false)) {
            // throw new \RuntimeException((string) data_get($res, 'message', 'Error'), 500);
            throw new \RuntimeException("No se pudo obtener el stock del articulo", 500);
        }
        $data = data_get($res, 'data', []);
        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? [];
        }

        return (array) $data;
    }
    public function fetchStock(array $infoRequest)
    {
        return collect($infoRequest)
            ->map(function ($params) {
                $res = $this->apiGpService->getStockWarehouse($params);

                if (!data_get($res, 'success', false)) {
                    throw new \RuntimeException((string) data_get($res, 'message', 'Error'), 500);
                }

                // Normaliza 'data' por si viene como objeto o lista de 1
                $data = data_get($res, 'data', []);
                if (is_array($data) && array_is_list($data)) {
                    $data = $data[0] ?? [];
                }

                return (array) $data;
            })
            ->values()
            ->all();
    }

    public function transformStockResponse($payload)
    {
        return array_values(array_map(function ($item) {
            $disp = (float) ($item['STOCK_DISPONIBLE'] ?? 0);
            return [
                'STO_ART_ARTICULO' => (string) ($item['ARTICULO'] ?? ''),
                'STO_ART_BODEGA' => (string) ($item['BODEGA'] ?? ''),
                'STO_ART_STOCK_DISPONIBLE' => number_format($disp, 2, '.', ''), // "0.00"
            ];
        }, $payload));
    }

    public function transformStockQuotationResponse($payload)
    {
        return array_values(array_map(function ($item) {
            $disp = (float) ($item['STOCK_DISPONIBLE'] ?? 0);
            return [
                'STO_ART_ARTICULO' => (string) ($item['ARTICULO'] ?? ''),
                'DESCRIPCION_ARTICULO' => (string) ($item['DESCRIPCION_ARTICULO'] ?? ''),
                'STO_ART_BODEGA' => (string) ($item['BODEGA'] ?? ''),
                'STO_ART_STOCK_DISPONIBLE' => number_format($disp, 2, '.', ''), // "0.00"
            ];
        }, $payload));
    }

    public function transformStockReservationResponse($payload)
    {
        return collect($payload)
            ->map(function($item){
                $disp = (float) (data_get($item, 'STOCK_DISPONIBLE', 0));
                return[
                    'STO_ART_ARTICULO' => (string) (data_get($item, 'ARTICULO', '')),
                    'DESCRIPCION_ARTICULO' => (string) (data_get($item, 'DESCRIPCION_ARTICULO', '')),
                    'STO_ART_BODEGA' => (string) (data_get($item, 'BODEGA', '')),
                    'STO_ART_STOCK_DISPONIBLE' => number_format($disp, 2, '.', ''),
                ];
            })->keyBy(function($item){

                return $item['STO_ART_ARTICULO'] . '_' . $item['STO_ART_BODEGA'];
            });
    }



    public function getWarehouse($data)
    {
        $query = GpRelacionPuntoVentaBodegaRelacionada::query()
            ->where('PTO_VTA_BOD_REL_PUNTO_VENTA', $data['puntodeventa_id'])
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '=', 'CONSULTA')
            ->orderBy('orden', 'ASC');
        return $query->get()
            ->pluck('PTO_VTA_BOD_REL_BODEGA', 'PTO_VTA_BOD_REL_BODEGA')
            ->toArray();
    }

    public function getWarehouseNotConsult($data, $excludeConsulta = null)
    {
        $query = GpRelacionPuntoVentaBodegaRelacionada::query()
            ->where('PTO_VTA_BOD_REL_PUNTO_VENTA', $data['puntodeventa_id'])
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA')
            ->orderBy('orden', 'ASC');

        if ($excludeConsulta) {
            $query->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA');
        }

        return $query->get()
            ->pluck('PTO_VTA_BOD_REL_BODEGA', 'PTO_VTA_BOD_REL_BODEGA')
            ->toArray();
    }



    /**
     * Busca el item L disponible para guardar en el detalle de pedido
     * ya que asigna el ART_CODIGO de este item L
     */
    public function getFirstAvailablePurchaseArticle($article, $excludeItemsL)
    {
        $itemsL = $this->getListArticlesOfTypeLWithLimit($article, $excludeItemsL);
        return $itemsL?->ART_CODIGO;
    }

    public function getListArticlesOfTypeLWithLimit($articulo, $excludeItemsL)
    {
        $base = GpArticulo::where('ART_CODIGO', $articulo['ART_CODIGO'])
            ->where('ART_ESTADO', 'ACTIVO')
            ->where('ART_TIPO_COMPRA', 'L')
            ->first(['ART_CLASE_ARTICULO', 'ART_IMP_COMPRA']);
        if (!$base)
            return null;

        $q = GpArticulo::query()
            ->where('ART_ESTADO', 'ACTIVO')
            ->where('ART_TIPO_COMPRA', 'L')
            ->where('ART_CLASE_ARTICULO', $base->ART_CLASE_ARTICULO)
            ->where('ART_IMP_COMPRA', $base->ART_IMP_COMPRA)
            ->where('disponible', 1);
        if (!empty($excludeItemsL)) {
            $q->whereNotIn('ART_CODIGO', $excludeItemsL);
        }
        return $q->orderBy('ART_DESCRIPCION', 'desc')
            ->orderBy('ART_CODIGO', 'asc')
            ->first();
    }

    public function checkValidateDisponibleItemL($articulo)
    {
        return GpArticulo::where('ART_ESTADO', 'ACTIVO')
            ->where('ART_TIPO_COMPRA', 'L')->where('ART_CODIGO', $articulo)
            ->where('disponible', 1)->exists();
    }

    public function markArticleUnavailable($articulo)
    {
        GpArticulo::where('ART_CODIGO', $articulo)
            ->where('ART_TIPO_COMPRA', 'L')
            ->update(['disponible' => 0]);
    }


    /** funcion para formatear y consultar reservado para
     *  actualizar el stock en pedido detalle stock
     */

    public function formatStockDisponibleApprovalCreditOrComercial($stockFormatted, $articulo, $orderId)
    {
        $bodegas = $stockFormatted
            ->pluck('STO_ART_BODEGA')
            ->unique()
            ->values()
            ->map(fn($i) => (string) $i)
            ->toArray();
        $reservas = $this->getReservedStockByWarehouses($articulo['ART_CODIGO'], $bodegas, $orderId, false, null);

        return collect($stockFormatted)
            ->filter(function ($item) use ($articulo) {
                return isset($item['STO_ART_STOCK_DISPONIBLE'], $item['STO_ART_BODEGA'], $item['STO_ART_ARTICULO']) &&
                    $item['STO_ART_ARTICULO'] === $articulo['ART_CODIGO'];
            })
            ->map(function ($stockItem) use ($articulo, $reservas) {
                $bodega = (string) $stockItem['STO_ART_BODEGA'];
                $stockBase = $stockItem['STO_ART_STOCK_DISPONIBLE'];

                $stockBase = ($articulo['ART_DECIMAL_CANTIDAD'] ?? 0) == 0
                    ? (int) $stockBase
                    : round($stockBase, 2);

                $resta = (float) ($reservas[$bodega] ?? 0);
                $stockItem['STO_ART_STOCK_DISPONIBLE'] = max(0, $stockBase - $resta);
                return $stockItem;
            })
            ->values()
            ->toArray();
    }



    /*** function para formatear y consultar el stock para guardado de pedido  */

    public function applyReservedQtyToAvailableStock(&$stock, $article, $orderId, $quotationId = null)
    {
        $bodega = (string) $stock['STO_ART_BODEGA'];
        $bodegas = [$bodega];
        $reservas = $this->getReservedStockByWarehouses($article['ART_CODIGO'], $bodegas, $orderId, true, $quotationId);

        if (
            isset($stock['STO_ART_STOCK_DISPONIBLE'], $stock['STO_ART_BODEGA'], $stock['STO_ART_ARTICULO']) &&
            $stock['STO_ART_ARTICULO'] === $article['ART_CODIGO']
        ) {
            $bodega = $stock['STO_ART_BODEGA'];
            $stockValue = $stock['STO_ART_STOCK_DISPONIBLE'];
            $stockValue = $article['ART_DECIMAL_CANTIDAD'] == 0
                ? (int) $stockValue
                : round((float) $stockValue, 2);

            $resta = (float) ($reservas[$bodega] ?? 0);
            $stock['STO_ART_STOCK_DISPONIBLE'] = max(0, $stockValue - $resta);
        }
    }




    /** funcion de api para consulta de lista de precio de articulos  */


    public function fetchListPricesArticle(string $article): array
    {
        $response = $this->apiGpService->fetchItemPriceListFromGp(
            [
                'articulo' => $article . ','
            ]
        );
        $data = data_get($response, 'data', []);
        return (array) $data;
    }
}
