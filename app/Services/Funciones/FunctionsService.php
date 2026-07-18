<?php

namespace App\Services\Funciones;

use App\Models\Articulos\GpArticulo;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Services\Api\ApiGpService;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;

class FunctionsService implements FunctionsServiceInterface
{



    public function __construct(
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly ApiGpService $apiGpService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly ApiBillingService $apiBillingService,
    ) {

    }

    public function getItemProviders($pedidoId, $item, $pendientes = false, $existentes = false): array
    {
        $providers = $this->orderRepository->getSuppliersByOrderAndItem($pedidoId, $item->id);
        if ($item->ART_DECIMAL_CANTIDAD == 0) {
            $item->ART_STOCK = (float) $item->ART_STOCK;
            $item->ART_CANTIDAD = (float) $item->ART_CANTIDAD;
        }

        // Procesar cada proveedor
        $providers->each(function ($provider) use (&$pendientes, &$existentes, $item) {
            $provider->ingresado ? $existentes = true : $pendientes = true;

            $orderDetails = $this->orderRepository->getPurchaseOrdersBySupplier($provider->id);

            $margin = ($provider->COSTO_UNITARIO / (1 - ($provider->MARGEN / 100))) / (1 - $item->descuento / 100);
            $provider->margen = number_format($margin, 4, '.', '');

            if ($item->ART_DECIMAL_CANTIDAD == 0) {
                $provider->CANTIDAD = (float) $provider->CANTIDAD;
                $provider->ingresado = (float) $provider->ingresado;
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


    public function getBodegas(string $warehouse, ?string $consulta = null)
    {
        $query = GpRelacionPuntoVentaBodegaRelacionada::where('PTO_VTA_BOD_REL_PUNTO_VENTA', $warehouse);
        if ($consulta) {
            $query->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA');
        }
        return $query->orderBy('orden', 'ASC')->pluck('PTO_VTA_BOD_REL_BODEGA', 'PTO_VTA_BOD_REL_BODEGA')->toArray();
    }


    /** funcion de recalcular pedido  */
    public function recalculateOrder(int $orderId)
    {
        $order      = GpPedido::find($orderId);
        $detalle    = GpPedidoDetalle::where('pedido_id', $orderId)->get();
        if ($detalle->isEmpty()) {
            return;
        }
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', $detalle->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        $subtotal           = 0.0;
        $totalDiscount      = 0.0;
        $netSubtotal        = 0.0;
        $totalIva           = 0.0;

        foreach ($detalle as $item) {
            $valorUnitario              = (float) $item->ART_VALOR;
            $cantidad                   = (float) $item->ART_CANTIDAD;
            $descuentoUnitario          = (float) $item->ART_DESCUENTO;
            $lineNet                    = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
            $lineaDiscount              = $descuentoUnitario * $cantidad;
            $lineaSubtotal              = $lineNet + $lineaDiscount;
            $impuestoPorcentaje         = (float) $articulosImpuesto[$item->ART_CODIGO] ?? 0;
            $lineaIva                   = $lineNet * ($impuestoPorcentaje / 100);
            $subtotal                   += $lineaSubtotal;
            $totalDiscount              += $lineaDiscount;
            $netSubtotal                += $lineNet;
            $totalIva                   += $lineaIva;
            $item->ART_IMP_VTA          = $impuestoPorcentaje;
            $item->ART_IVA              = $lineaIva;
            $item->save();
        }

        $order->subtotal                = round($subtotal, 2);
        $order->descuento               = round($totalDiscount, 2);
        $order->subtotalmenosdescuento  = round($netSubtotal, 2);
        $order->iva                     = round($totalIva, 2);
        $order->total                   = round($netSubtotal + $totalIva, 2);
        $order->save();
    }

    /** funcion para recalcular iva devuelve en memoria el detalle actualizado con el iva */
    public function recalculateIva($detalle)
    {
        if ($detalle->isEmpty()) {
            return;
        }

        //obtiene todos los codigos de atriculos únicos
        $articulosCodigo = collect($detalle)->filter(function ($item): bool {
            return isset($item->ART_CODIGO);
        })->pluck('ART_CODIGO')->unique()->values();
        $impuesto = GpArticulo::whereIn('ART_CODIGO', $articulosCodigo)->pluck('ART_IMP_VTA', 'ART_CODIGO');

        return $detalle->each(function ($item) use ($impuesto) {
            if (isset($item->ART_CODIGO) && $impuesto->has($item->ART_CODIGO)) {
                $item->ART_IMP_VTA = $impuesto[$item->ART_CODIGO]; // solo actualiza en memoria
            }
        });
    }


    /** obtrener clientes getcliente - para recuperacion de cotizacion */

    public function getClientCheckAvailableQuota(string $ruc)
    {
        $response = $this->apiBillingService->consultCheckAvailableQuotaClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return data_get($response, 'data', []);
    }


    public function getClientDocumentExpired(string $ruc)
    {
        $response = $this->apiBillingService->consultDocumentExpiredClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return $response['data'] ?? [];
    }

    public function getClientDocumentWithoutSupport(string $ruc)
    {
        $response = $this->apiBillingService->consultDocumentWithoutSupportClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return data_get($response, 'data', []);
    }

    public function getListClientDocumentExpired(string $ruc)
    {
        $response = $this->apiBillingService->consultListDocumentExpiredClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return data_get($response, 'data', []);

    }

    public function getListClientDocumentWithoutSupport(string $ruc)
    {
        $response = $this->apiBillingService->consultListDocumentWithoutSupportClient([
            'ruc' => $ruc,
            'idCompania' => config('services.api_gp.company'),
        ]);
        return data_get($response, 'data', []);

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
                continue;
            }

            if ($this->stockWarehouseService->checkArticuloDisponible($article)) {
                return $article->ART_CODIGO;
            }
        }
        return null;
    }





    public function like_match($pattern, $subject)
    {
        $pattern = str_replace('%', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match("/^{$pattern}$/i", $subject);
    }
}
