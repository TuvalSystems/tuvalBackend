<?php

namespace App\Services\Venta\Cotizaciones;

use App\Models\GpCotizacion;
use App\Models\Ventas\Cotizaciones\HiCotizacion;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Venta\Cotizaciones\Contracts\QuotationHistoryCreatorServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuotationHistoryCreatorService implements QuotationHistoryCreatorServiceInterface
{
    public function __construct(
        protected readonly QuotationBuilderInterface $builder
    ) {}

    public function createQuoteHistory(GpCotizacion $quote): void
    {
        DB::transaction(function () use ($quote) {
            $quote->load(['detallecotizacion.stock', 'detallecotizacion.proveedores']);
            $lastVersion = HiCotizacion::where('cot_id', $quote->id)->max('co_his');
            $version = ((int) $lastVersion) + 1;
            $history = $this->builder->formPayloadHistoryHeaderQuote($quote, $version);
            $history->save();
            $this->createHistoryDetailQuotation(
                $quote,
                $history,
                $quote->detallecotizacion
            );
        });
    }


    public function createHistoryDetailQuotation(GpCotizacion $quote, HiCotizacion $hQuote, Collection $details,)
    {
        foreach ($details as  $det) {
            $hDet = $this->builder->formPayloadHistoryDetailQuote($quote->id, $det->id,$hQuote->id, $det);
            $hDet->save();

            /** proveedores */
            if ($det->proveedores->isNotEmpty()) {
                $this->createHistoryDetailProvider($hQuote->id, $hDet->id, $quote->id, $det->id, $det->proveedores);
            }

            /** stock */
            if ($det->stock->isNotEmpty()) {
                $this->createHistoryDetailStock($hQuote->id, $hDet->id, $quote->id, $det->id, $det->stock);
            }
        }
    }

    public function createHistoryDetailProvider(int $hquoteId, int $hdetailId, int $quoteId, int $detId, Collection $providers): void
    {
        foreach ($providers as  $prov) {
            $hProv = $this->builder->formPayloadHistoryDetailQuoteProvider($hquoteId, $hdetailId, $quoteId, $detId, $prov);
            $hProv->save();
        }
    }

    public function createHistoryDetailStock(int $hquoteId, int $hdetailId, int $quoteId, int $detId, Collection $stocks): void
    {
        foreach ($stocks as $stock) {
            $hStock = $this->builder->formPayloadHistoryDetailQuoteStock($hquoteId, $hdetailId, $quoteId, $detId, $stock);
            $hStock->save();
        }
    }
}
