<?php

namespace App\Services\Venta\Utils;

use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
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



    public function getDetailQuotationOrOrder($data, $encera = false, $copy = false)
    {
        $clientes = $this->functionService->getClient($data);
        $detalles = $this->getQuotationDetailWithSuppliers($data['id'], $copy);
        $this->resetQuotationDiscount($detalles, $clientes, $encera);
        $arrayArticulos = $detalles->pluck('ART_CODIGO')->map(fn($artCode) => ['ART_CODIGO' => $artCode])->toArray();
        $listaPreciosWs = $this->getPriceListByItems($arrayArticulos);

        $detalles->each(function ($item) use ($listaPreciosWs, $clientes) {
            if ($item->ART_TIPO_COMPRA != 'L') {
                $item->ART_VALOR = $listaPreciosWs[$item->ART_CODIGO][$clientes->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'] ?? 0;
                $item->ART_VALOR_ORIGINAL =  $item->ART_VALOR;
            }
            return $item;
        });

        return $detalles;
    }

    public function getQuotationDetailWithSuppliers($quotationId, $copy)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores = GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
        $details->each(function ($item) use ($proveedores, $copy) {
            $item->proveedores = $copy ? collect() : collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
        });

        return $details;
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
}
