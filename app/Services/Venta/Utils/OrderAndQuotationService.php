<?php

namespace App\Services\Venta\Utils;

use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpPedidoDetalle;
use App\Models\GpTempCart;
use App\Services\Api\MswService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;

class OrderAndQuotationService implements OrderAndQuotationServiceInterface
{

    protected $apiGpService;
    protected $functionService;
    protected $MsWebService;
    protected $stockWarehouseService;

    public function __construct(
        ApiGpService $apiGpService,
        FunctionsServiceInterface $functionService,
        MswService $MsWebService,
        StockWarehouseServiceInterface $stockWarehouseService
    ) {
        $this->apiGpService = $apiGpService;
        $this->functionService = $functionService;
        $this->MsWebService = $MsWebService;
        $this->stockWarehouseService = $stockWarehouseService;
    }



    public function getDetailQuotationOrOrder($cliente, $data, $encera = false, $copy = false, $type)
    {

        $warehouse = $this->functionService->getBodegas($data['PTO_VTA_ID'], 1);
        $detalles = $this->getQuotationDetailWithSuppliersType($data['id'], $copy, $type, $warehouse);
        $this->resetQuotationDiscount($detalles, $cliente, $encera);
        $arrayArticulos = $detalles->pluck('ART_CODIGO')->map(fn($artCode) => ['ART_CODIGO' => $artCode])->toArray();
        $listaPreciosWs = $this->getPriceListByItems($arrayArticulos);

        $detalles->each(function ($item) use ($listaPreciosWs, $cliente, $encera) {
            if ($item->ART_TIPO_COMPRA != 'L') {
                if ($encera) {
                    $item->ART_VALOR = $listaPreciosWs[$item->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'] ?? 0;
                    $item->ART_VALOR_ORIGINAL =  $item->ART_VALOR;
                }
            }
            return $item;
        });

        return $detalles;
    }

    public function getQuotationDetailWithSuppliersType($quotationId, $copy, $type, $warehouse)
    {
        return $type == 'cotizacion' ? $this->getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
            : $this->getQuotationDetailWithSuppliersTypeOrder($quotationId, $warehouse);
    }


    public function getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores = GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
        $details->each(function ($item) use ($proveedores, $copy, $warehouse) {
            $item->proveedores = $copy ? collect() : collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
            $item->stock = $this->getFormattedStocksQuotation($item, $warehouse);
        });
        return $details;
    }

    public function getQuotationDetailWithSuppliersTypeOrder($quotationId, $warehouse)
    {
        $details = GpPedidoDetalle::where('pedido_id', $quotationId)->get();
        $details->each(function ($item) use ($warehouse) {
            $item->proveedores = collect();
            $item->stock = $this->getFormattedStocksQuotation($item, $warehouse);
        });
        return $details;
    }


    /**
     * @return array
     * Retorna una array de stock formateado para volver a facturar
     * o copiar pedido de forma termporal ya que en al consultar el stock
     *  recibe el stock real, pero con el seleccionado en 0
     */
    public function getFormattedStocksQuotation($article, $warehouse)
    {
        $stocks = [];
        foreach ($warehouse as $wrh) {
            $stocks[] = [
                'STO_ART_BODEGA' => $wrh,  //ya viene el nombre de la bodega INMACONSA o x Bodega
                'seleccionado' => 0,
                'STO_ART_STOCK_DISPONIBLE' => $article->ART_STOCK ?? 0, // stock del articulo cuando se copia o volver a facturar
            ];
        }
        return $stocks;
    }

    public function resetQuotationDiscount(&$detalles, $cliente, $encera)
    {
        if ($encera) {
            $detalles->each(function ($item) use ($cliente) {
                $item->descuento = $cliente->CLI_DSTO_COMERCIAL;
                $item->descuento_original = $cliente->CLI_DSTO_COMERCIAL;
                $item->ART_DESCUENTO = 0;
                $item->modificador = 0;
                $item->ART_DESCRIPCION = $item->ART_DESCRIPCION_ORIGINAL;
            });
        }
    }


    public function getPriceListByItems($arArticulos)
    {
        $prices =  $this->MsWebService->listaprecios($arArticulos);
        $indexedPrices  = [];
        foreach ($prices as $price) {
            $articleCode = $price['LST_PRC_ARTICULO'];
            $priceId = $price['LST_PRC_ID'];
            $indexedPrices[$articleCode][$priceId] = $price;
        }
        return $indexedPrices;
    }


    public function clearTempDataQuotation($sucursalId, $PTO_VTA_ID, $clienteId, $userId)
    {
        GpTempCart::where('sucursal_id', $sucursalId)
            ->where('puntodeventa_id', $PTO_VTA_ID)
            ->where('cliente_id', $clienteId)
            ->where('user_id', $userId)
            ->delete();
    }
}
