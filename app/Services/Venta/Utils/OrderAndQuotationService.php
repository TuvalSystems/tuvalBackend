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
            // $item->stock = $copy ? $this->getFormattedStocksQuotation($quotationId, $item->id, 'cotizacion') : $this->getStocksRequestQuotation($quotationId, $item->id, 'cotizacion');
            $item->stock =  $this->getFormattedStocksQuotation($quotationId, $item->id, 'cotizacion');
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
    public function getQuotationDetailWithSuppliersTypeOrder($quotationId, $copy,  $warehouse)
    {
        $details = GpPedidoDetalle::where('pedido_id', $quotationId)->get();
        $details->each(function ($item) use ($warehouse, $quotationId, $copy) {
            $item->proveedores = collect();
            // $item->stock = $copy ? $this->getFormattedStocksQuotation($quotationId, $item->id, 'pedido') : $this->getStocksRequestQuotation($quotationId, $item->id, 'pedido');
            $item->stock =  $this->getFormattedStocksQuotation($quotationId, $item->id, 'pedido');
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
            $stock  = GpPedidoDetalleStock::where('pedido_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
            return $this->formatStockCollection($stock, 'pedido_id');
        }
        $stock  = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
        return $this->formatStockCollection($stock, 'cotizacion_id');
    }

    public function formatStockCollection(array $items, string $relationKey): array
    {
        return collect($items)->map(function ($item) use ($relationKey) {
            return [
                'id' => $item['id'],
                $relationKey => $item[$relationKey],
                'detalle_id' => $item['detalle_id'],
                'STO_ART_BODEGA' => $item['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => 0,
                'seleccionado' =>  0 //$item['seleccionado'],
            ];
        })->toArray();
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
}
