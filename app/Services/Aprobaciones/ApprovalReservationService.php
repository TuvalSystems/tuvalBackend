<?php

namespace App\Services\Aprobaciones;

use App\Models\GpCotizacionDetalle;
use App\Models\reservas\GpReserva;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Facades\Log;

class ApprovalReservationService implements ApprovalReservationServiceInterface
{

    public function __construct(protected readonly StockWarehouseServiceInterface $stockService)
    {
        // Inyecta dependencias si es necesario
    }


    public function checkValidateApprovalReservation(int $id): string|null
    {
        $reserva = GpReserva::find($id);
        $detalleQuote = $this->getDetailReserveWithStock($reserva->cotizacion_id);
        $flatDetails = $this->stockFormatterForUpdateApproval($detalleQuote);
        $parmas = $this->stockFormatterParametersUpdateApproval($flatDetails);
        $stocksResult = $this->getStockWarehouseAsyncAndFormantter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
        return $this->getAlertWithoutStockArticles($detalleQuote, $newFormatStock, null, null);

    }

    public function getDetailReserveWithStock($quoteId)
    {
        return GpCotizacionDetalle::with([
            'stock' => function ($q) {
                $q->where('seleccionado', '>', 0);
            }
        ])
            ->where('cotizacion_id', $quoteId)
            ->where('ART_TIPO_COMPRA', '!=', 'L')->get();
    }


    public function stockFormatterForUpdateApproval($detalles)
    {
        return $detalles->pluck('stock')
            ->flatten()
            ->map(function ($stock) use ($detalles) {
                $detalle = $detalles->firstWhere('id', $stock->detalle_id);
                return [
                    'stock_id' => $stock->id,
                    'cotizacion_id' => $stock->cotizacion_id,
                    'detalle_id' => $stock->detalle_id,
                    'STO_ART_ARTICULO' => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA' => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE' => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado' => $stock->seleccionado
                ];
            })->toArray();
    }


    public function stockFormatterParametersUpdateApproval($newDetails)
    {
        return collect($newDetails)
            ->map(fn($d) => ['articulo' => $d['STO_ART_ARTICULO'], 'bodega' => $d['STO_ART_BODEGA']])
            ->unique()
            ->values()
            ->toArray();
    }


    public function getStockWarehouseAsyncAndFormantter($parmas)
    {
        $stocksResult = $this->stockService->fetchStockBatchWarehouses($parmas);
        Log::info("Resultado de stock para aprobacion - reservas: ", [
            'stocksResult' => $stocksResult
        ]);
        return $this->stockService->transformStockResponse($stocksResult);
    }


    public function formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult)
    {
        $stockMap = collect($stocksResult)->keyBy(function ($stock) {
            return $stock['STO_ART_ARTICULO'] . '|' . $stock['STO_ART_BODEGA'];
        });
        return collect($flatDetails)->map(function ($detail) use ($stockMap) {
            $key = $detail['STO_ART_ARTICULO'] . '|' . $detail['STO_ART_BODEGA'];
            $stockDisponible = $stockMap->get($key)['STO_ART_STOCK_DISPONIBLE'] ?? 0;
            return [
                'stock_id' => $detail['stock_id'],
                'cotizacion_id' => $detail['cotizacion_id'],
                'detalle_id' => $detail['detalle_id'],
                'STO_ART_ARTICULO' => $detail['STO_ART_ARTICULO'],
                'STO_ART_BODEGA' => $detail['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE' => $stockDisponible,
                'seleccionado' => $detail['seleccionado']
            ];
        });
    }


    public function getAlertWithoutStockArticles($detalles, $newFormatStock, $orderId = null, $quoteReservedOriginId = null): string|null
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }

        if ($allFormattedStock->isEmpty()) {
            return null;
        }
        $alert = null;
        foreach ($allFormattedStock as $stock) {
            if ($stock['STO_ART_STOCK_DISPONIBLE'] < $stock['seleccionado']) {
                $alert .= "El artículo {$stock['STO_ART_ARTICULO']} tiene stock insuficiente. " .
                    "Stock disponible: {$stock['STO_ART_STOCK_DISPONIBLE']} - " .
                    "Cantidad solicitada: {$stock['seleccionado']}<br>";
            }
        }
        return $alert;
    }

}
