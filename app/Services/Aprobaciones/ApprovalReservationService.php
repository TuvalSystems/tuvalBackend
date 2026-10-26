<?php

namespace App\Services\Aprobaciones;

use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Ventas\Reservas\GpReserva;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ApprovalReservationService implements ApprovalReservationServiceInterface
{

    public function __construct(protected readonly StockWarehouseServiceInterface $stockService)
    {
        // Inyecta dependencias si es necesario
    }


    public function checkValidateApprovalReservation(int $id): ?string
    {
        $reserva        = GpReserva::findOrFail($id);
        $detalleQuote   = $this->getDetailReserveWithStock($reserva->cotizacion_id);
        $flatDetails    = $this->stockFormatterForUpdateApproval($detalleQuote);
        $parmas         = $this->stockFormatterParametersUpdateApproval($flatDetails);
        $stocksResult   = $this->getStockWarehouseAsyncAndFormantter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
        return $this->getAlertWithoutStockArticles($detalleQuote, $newFormatStock, null, null);
    }

    protected function getDetailReserveWithStock(int $quoteId): EloquentCollection
    {
        return GpCotizacionDetalle::with([
            'stock' => function ($q) {
                $q->where('seleccionado', '>', 0);
            }
        ])
            ->where('cotizacion_id', $quoteId)
            ->whereNotIn('ART_TIPO_COMPRA', ['L', 'SERV'])->get();
    }


    protected function stockFormatterForUpdateApproval(EloquentCollection $detalles): array
    {
        return $detalles->pluck('stock')
            ->flatten()
            ->map(function ($stock) use ($detalles) {
                $detalle = $detalles->firstWhere('id', $stock->detalle_id);
                return [
                    'stock_id'                  => $stock->id,
                    'cotizacion_id'             => $stock->cotizacion_id,
                    'detalle_id'                => $stock->detalle_id,
                    'STO_ART_ARTICULO'          => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA'            => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE'  => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado'              => $stock->seleccionado
                ];
            })->toArray();
    }


    protected function stockFormatterParametersUpdateApproval(array $newDetails): array
    {
        return collect($newDetails)
            ->map(fn($d) => ['articulo' => $d['STO_ART_ARTICULO'], 'bodega' => $d['STO_ART_BODEGA']])
            ->unique()
            ->values()
            ->toArray();
    }


    protected function getStockWarehouseAsyncAndFormantter(array $parmas): array
    {
        $stocksResult = $this->stockService->fetchStockBatchWarehouses($parmas);
        Log::info("Resultado de stock para aprobacion - reservas: ", [
            'stocksResult' => $stocksResult
        ]);
        return $this->stockService->transformStockResponse($stocksResult);
    }


    protected function formattedFlatDetailWithStockGeneral(array $flatDetails, array $stocksResult): Collection
    {
        $stockMap = collect($stocksResult)->keyBy(function ($stock) {
            $art    = data_get($stock, 'STO_ART_ARTICULO', '');
            $bodega = data_get($stock, 'STO_ART_BODEGA', '');
            return $art . '|' . $bodega;
        });
        return collect($flatDetails)->map(function ($detail) use ($stockMap) {
            $key = $detail['STO_ART_ARTICULO'] . '|' . $detail['STO_ART_BODEGA'];
            $stockDisponible = $stockMap->get($key)['STO_ART_STOCK_DISPONIBLE'] ?? 0;
            return [
                'stock_id'                  => data_get($detail, 'stock_id', null),
                'cotizacion_id'             => data_get($detail, 'cotizacion_id', null),
                'detalle_id'                => data_get($detail, 'detalle_id', null),
                'STO_ART_ARTICULO'          => data_get($detail, 'STO_ART_ARTICULO', null),
                'STO_ART_BODEGA'            => data_get($detail, 'STO_ART_BODEGA', null),
                'STO_ART_STOCK_DISPONIBLE'  => $stockDisponible,
                'seleccionado'              => data_get($detail, 'seleccionado', 0)
            ];
        });
    }


    protected function getAlertWithoutStockArticles(EloquentCollection $detalles, Collection $newFormatStock, ?int $orderId = null, ?int $quoteReservedOriginId = null): ?string
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, false, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }

        if ($allFormattedStock->isEmpty()) {
            return null;
        }

        $alert = "";
        $groupStock = $allFormattedStock->unique('stock_id')->groupBy(fn($item) => $item['STO_ART_ARTICULO'] . '|' . $item['STO_ART_BODEGA']);
        foreach ($groupStock as $group) {
            $firstItemStock     = $group->first();
            $art                = data_get($firstItemStock, 'STO_ART_ARTICULO', '');
            $bodega             = data_get($firstItemStock, 'STO_ART_BODEGA', '');
            $disponibleReal     = (float) data_get($firstItemStock, 'STO_ART_STOCK_DISPONIBLE', 0);


            $totalRequerido = $group->sum('seleccionado');

            if ($disponibleReal < $totalRequerido) {
                $alert .= "El artículo <b>{$art}</b> en bodega <b>{$bodega}</b> tiene stock insuficiente.<br>" .
                    "Stock Disponible: <b>{$disponibleReal}</b> <br>" .
                    "Cantidad Requerida (Suma filas): <b>{$totalRequerido}</b><br><hr>";
            }
        }
        return empty($alert) ? null : $alert;
    }
}
