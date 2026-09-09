<?php

namespace App\Services\Imports;

use App\Models\GpCotizacion;
use App\Services\Api\ApiGpService;
use App\Services\Imports\Contracts\QuoteImportsInterface;
use Illuminate\Support\Collection;

class QuoteImports implements QuoteImportsInterface
{
    private const INPROCESS = 3;
    private const PROCESSED = 1;
    private const ERROR = 2;
    public function __construct(protected readonly ApiGpService $api) {}



    public function getQuotesByUpdate(string $start, string $end)
    {
        return GpCotizacion::query()
            ->select(['id', 'cotizacion', 'estado'])
            ->where('import_job', 0)
            ->whereNotNull('cotizacion_venta_motivo_id')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id');
    }

    public function markQuotesAsInProcess(array $ids): int
    {
        return GpCotizacion::whereIn('id', $ids)
            ->where('import_job', 0)
            ->update(['import_job' => self::INPROCESS]);
    }


    public function updateSqlServerQuotesFromPayload(Collection $payload)
    {
        $quotes = $payload->values();
        $quoteIds = $quotes->pluck('id')->filter()->values()->toArray();
        $quotesPayload = $this->payloadQuotes($payload);

        if (empty($quoteIds) || empty($quotesPayload)) {
            return [
                'success' => true,
                'processed_count' => 0,
                'error_count' => 0,
                'response' => null,
            ];
        }

        $response = $this->updateQuoteStatus($quotesPayload);
        $success = data_get($response, 'success', false);
        if (!$success) {
            GpCotizacion::whereIn('id', $quoteIds)
                ->update(['import_job' => self::ERROR]);

            return [
                'success' => false,
                'processed_count' => 0,
                'error_count' => count($quoteIds),
                'response' => $response,
            ];
        }

        [$processedIds, $errorIds] = $this->resolveBatchResultIds($quotes, $response);

        if (!empty($processedIds)) {
            GpCotizacion::whereIn('id', $processedIds)
                ->update(['import_job' => self::PROCESSED]);
        }

        if (!empty($errorIds)) {
            GpCotizacion::whereIn('id', $errorIds)
                ->update(['import_job' => self::ERROR]);
        }

        return [
            'success' => empty($errorIds),
            'processed_count' => count($processedIds),
            'error_count' => count($errorIds),
            'response' => $response,
        ];
    }


    protected function payloadQuotes(Collection $quotes): array
    {
        return $quotes->map(function ($quote) {
            return [
                'cotizacion' => data_get($quote, 'cotizacion'),
                'estado'    => data_get($quote, 'estado'),
            ];
        })->values()->toArray();
    }


    protected function updateQuoteStatus(array $payload): array
    {
        $response = $this->api->updateAllQuoteStatusSql($payload);

        return is_array($response) ? $response : [
            'success' => false,
            'message' => 'Respuesta inválida del API al actualizar cotizaciones.',
            'data' => $response,
        ];
    }

    protected function resolveBatchResultIds(Collection $quotes, array $response): array
    {
        $results = data_get($response, 'data.resultadoBatch');

        if (is_array($results) && !empty($results)) {
            $processedIds = [];
            $errorIds = [];

            foreach ($quotes as $index => $quote) {
                $id = data_get($quote, 'id');

                if (!$id) {
                    continue;
                }

                if ($this->isSuccessfulBatchItem($results[$index] ?? null)) {
                    $processedIds[] = $id;
                    continue;
                }

                $errorIds[] = $id;
            }

            return [$processedIds, $errorIds];
        }

        $ids = $quotes->pluck('id')->filter()->values()->toArray();
        $totalUpdated = data_get($response, 'data.totalActualizados');

        if (is_numeric($totalUpdated) && (int) $totalUpdated < count($ids)) {
            return [[], $ids];
        }

        return [$ids, []];
    }

    protected function isSuccessfulBatchItem($result): bool
    {
        if (is_bool($result)) {
            return $result;
        }

        if (is_numeric($result)) {
            return (int) $result > 0;
        }

        return !empty($result);
    }
}
