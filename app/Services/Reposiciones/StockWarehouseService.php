<?php

namespace App\Services\Reposiciones;

use App\Models\GpTempCart;
use App\Models\GpDatosItemsL;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class StockWarehouseService implements StockWarehouseServiceInterface
{

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
            ->whereNull('completado')
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
        if ($this->hasActiveOrders($articulo)) {
            return false;
        }
        // Verificar pedidos facturados no despachados
        if ($this->hasPendingInvoicedOrders($articulo)) {
            return false;
        }
        // Verificar artículos de clase especial
        if (isset($articulo['ART_CLASE_ARTICULO'])) {
            if ($this->hasRecentDispatchedOrders($articulo)) {
                return false;
            }
        } else {
            return false;
        }
        //Verificar si esta en carrito temporal
        if ($this->isTemporalCart($articulo)) {
            return false;
        }
        return true;
    }

    public function hasActiveOrders($articulo)
    {
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->whereIn('gp_pedidos.estado', ['espera', 'traslado', 'orden_de_compra'])
            ->where('ART_CODIGO', $articulo['ART_CODIGO'])
            ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->exists();
    }

    public function hasPendingInvoicedOrders($articulo)
    {
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where('gp_pedidos.estado', 'facturado')
            ->where('ART_CODIGO', $articulo['ART_CODIGO'])
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado')
                    ->orWhere('gp_pedidos.despachado', 0);
            })
            ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->exists();
    }

    public function isTemporalCart($articulo)
    {
        return GpTempCart::where('ART_CODIGO', $articulo['ART_CODIGO'])->exists();
    }

    public function hasRecentDispatchedOrders($articulo)
    {
        $datosL = GpDatosItemsL::where('DATOS_ITEMS_L_CLASE', $articulo['ART_CLASE_ARTICULO'])->first();
        if (!$datosL) {
            return false;
        }
        return GpPedidoDetalle::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_pedido_detalles.pedido_id')
            ->where('gp_pedidos.estado', 'facturado')
            ->where('ART_CODIGO', $articulo['ART_CODIGO'])
            ->where('gp_pedidos.despachado', 1)
            ->where('gp_pedidos.id', '>', env('ULTIMO_PEDIDO_BUSQUEDA'))
            ->whereDate('fecha_factura', '>', now()->subDays($datosL['DATOS_ITEMS_L_DIA_MAXIMO'])->startOfDay())
            ->exists();
    }
    public function formatResultStocks($stockResults)
    {
         if (!is_array($stockResults)) {
            return [];
        }
        return collect($stockResults)
            ->filter(fn($item) => is_array($item))
            ->flatten(1)
            ->map(fn($item) => [
                'STO_ART_ARTICULO' => $item['ARTICULO'] ?? '',
                'STO_ART_BODEGA' => $item['BODEGA'] ?? '',
                'STO_ART_STOCK_DISPONIBLE' => number_format((float) $item['STOCK_DISPONIBLE'], 2, '.', '') ?? 0,
            ])
            ->values()
            ->toArray();
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
}
