<?php

namespace App\Services\Venta\Utils;

use App\Models\GpRegistroCopia;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpTempCart;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;

class OrderAndQuotationService implements OrderAndQuotationServiceInterface
{
    public function __construct(
        protected readonly ApiGpService $apiGpService,
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService
    ) {}



    public function getDetailQuotationOrOrder($data, $cliente, $type, $encera = false, $copy = false)
    {

        $warehouse = $this->functionService->getBodegas($data->PTO_VTA_ID, 1);
        $detalles = $this->getQuotationDetailWithSuppliersType($data->id, $copy, $type, $warehouse);
        $this->resetQuotationDiscount($detalles, $cliente, $encera, $data->BOD_BODEGA);
        if ($encera) {
            $detalles->each(function ($item) use ($cliente) {
                if ($item->ART_TIPO_COMPRA != 'L') {
                    $precioActual = data_get($this->getPriceListByItem($item->ART_CODIGO, $cliente->CLI_NIVEL_PRECIO), 'valor', 0);
                    $item->ART_VALOR = $precioActual;
                    $item->ART_VALOR_ORIGINAL = $precioActual;
                }
                return $item;
            });
        }

        return $detalles;
    }

    public function getQuotationDetailWithSuppliersType($quotationId, $copy, $type, $warehouse)
    {
        return $type == 'cotizacion' ? $this->getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
            : $this->getQuotationDetailWithSuppliersTypeOrder($quotationId, $copy, $warehouse);
    }



    /** cotizacion */
    public function getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores = GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
        $details->each(function ($item) use ($proveedores, $copy, $warehouse, $quotationId) {
            $item->proveedores = $copy ? collect() : collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
            $item->stock = $this->getFormattedStocksQuotation($quotationId, $item->id, 'cotizacion');
        });
        return $details;
    }
    public function getStocksRequestQuotation($quotationId, $detailId, $typeDetailStock)
    {
        if ($typeDetailStock == 'pedido') {
            return GpPedidoDetalleStock::where('pedido_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
        }
        return GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
    }

    /**pedido */
    public function getQuotationDetailWithSuppliersTypeOrder($quotationId, $copy, $warehouse)
    {
        $details = GpPedidoDetalle::where('pedido_id', $quotationId)->get();
        $details->each(function ($item) use ($warehouse, $quotationId, $copy) {
            $item->proveedores = collect();
            $item->stock = $this->getFormattedStocksQuotation($quotationId, $item->id, 'pedido');
        });
        return $details;
    }


    /**
     * @return array
     * Retorna una array de stock formateado para volver a facturar
     * o copiar pedido de forma termporal ya que en al consultar el stock
     *  recibe el stock real, pero con el seleccionado en 0
     */
    public function getFormattedStocksQuotation($quotationId, $detailId, $typeDetailStock)
    {
        if ($typeDetailStock == 'pedido') {
            $stock = GpPedidoDetalleStock::where('pedido_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
            return $this->formatStockCollection($stock, 'pedido_id');
        }
        $stock = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
        return $this->formatStockCollection($stock, 'cotizacion_id');
    }

    public function formatStockCollection(array $items, string $relationKey): array
    {
        return collect($items)->map(function ($item) use ($relationKey) {
            return [
                'id'                        => null,
                $relationKey                => null,
                'detalle_id'                => null,
                'STO_ART_BODEGA'            => $item['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE'  => 0,
                'seleccionado'              => 0 //$item['seleccionado'],
            ];
        })->toArray();
    }

    public function resetQuotationDiscount(&$detalles, $cliente, $encera, $warehouse)
    {
        if ($encera) {
            $detalles->each(function ($item) use ($cliente, $warehouse) {
                $item->descuento                    = $cliente->CLI_DSTO_COMERCIAL;
                $item->descuento_original           = $cliente->CLI_DSTO_COMERCIAL;
                $item->ART_DESCUENTO                = 0;
                $item->modificador                  = 0;
                $item->ART_DESCRIPCION_ORIGINAL     = $this->restoreDescriptionItemQuotation($item->ART_CODIGO, $warehouse) ?? $item->ART_DESCRIPCION_ORIGINAL;
                $item->ART_DESCRIPCION              = $item->ART_DESCRIPCION_ORIGINAL;
            });
        }
    }

    public function restoreDescriptionItemQuotation($articulo, $bodega)
    {
        $params = ['articulo' => $articulo, 'bodega' => $bodega];
        $articulo = $this->stockWarehouseService->fetchStockByOnlyWarehouse($params);
        return $articulo['DESCRIPCION_ARTICULO'] ?? null;
    }


    public function getPriceListByItem($articulo, $nivelPrice)
    {
        $prices = $this->getApiPriceArticle($articulo);
        return collect($prices)->firstWhere('idLista', $nivelPrice);
    }
    public function getApiPriceArticle($article)
    {
        $response = $this->apiGpService->fetchItemPriceListFromGp([
            'item' => $article,
        ]);
        $data = data_get($response, 'data', []);
        return (array) $data;
    }






    /** funcion general para el registro de copia para cotizacion y pedido
     * @return int
     */
    public function createRegisterCopy($dataId, $user, $typeCopy, $typeRegisterCopy)
    {
        $registroCopia = new GpRegistroCopia([
            'user_id' => $user->id,
            'USERID' => $user->USERID,
            'tipo' => $typeRegisterCopy,

        ]);
        if ($typeCopy == 'pedido') {
            $registroCopia->pedido_origen = $dataId;
        } else {
            $registroCopia->cotizacion_origen = $dataId;
        }
        $registroCopia->save();
        return $registroCopia->id;
    }

    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGpService->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }


    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId, $quotationId = null)
    {
        $params = [
            'articulo' => $detailOrder->ART_CODIGO,
            'bodega' => $stock['STO_ART_BODEGA'],
        ];
        $stocksResult = $this->stockWarehouseService->fetchStockByOnlyWarehouse($params);
        $this->assignmentDisponibleStock($stock, $stocksResult, $detailOrder);
        $this->stockWarehouseService->applyReservedQtyToAvailableStock($stock, $detailOrder, $orderId, $quotationId);
        return $stock;
    }


    public function assignmentDisponibleStock(&$stock, $stockResponseFormatted, $detailOrder)
    {
        $stock['STO_ART_STOCK_DISPONIBLE'] = $stockResponseFormatted['STOCK_DISPONIBLE'] ?? 0;
        $stock['STO_ART_ARTICULO'] = $detailOrder->ART_CODIGO;
    }


    /** funciones
     *  para visualizar el porcentaje de cambio de precio y descuento de un articulo
     */

    public function calculateDiscountFraction(float $originalPrice, float $currentPrice)
    {
        if ($originalPrice == 0.0) {
            return 0.0;
        }
        return ($currentPrice - $originalPrice) / $originalPrice;
    }

    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount)
    {
        $original = $originalDiscount / 100;
        $current = $currentDiscount / 100;
        return $current - $original;
    }
}
