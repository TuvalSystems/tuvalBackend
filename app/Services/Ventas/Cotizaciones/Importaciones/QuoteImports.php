<?php

namespace App\Services\Ventas\Cotizaciones\Importaciones;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Ventas\Cotizaciones\Importaciones\Contracts\QuoteImportsInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
            if (!$this->shouldRetryIndividually($response)) {
                GpCotizacion::whereIn('id', $quoteIds)
                    ->update(['import_job' => self::ERROR]);

                $failedRecords = $this->failedRecordsFromQuotes($quotes, $response);
                $this->logFailedQuoteUpdates($failedRecords, $response);

                return [
                    'success' => false,
                    'processed_count' => 0,
                    'error_count' => count($quoteIds),
                    'failed_records' => $failedRecords,
                    'response' => $response,
                ];
            }

            return $this->updateQuotesIndividually($quotes, $response);
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

    protected function updateQuotesIndividually(Collection $quotes, array $batchResponse): array
    {
        $processedIds = [];
        $errorIds = [];
        $failedRecords = [];

        foreach ($quotes as $quote) {
            $id = data_get($quote, 'id');

            if (!$id) {
                continue;
            }

            $response = $this->updateQuoteStatus($this->payloadQuotes(collect([$quote])));
            $success = data_get($response, 'success', false);

            if ($success) {
                [$itemProcessedIds, $itemErrorIds] = $this->resolveBatchResultIds(collect([$quote]), $response);

                if (!empty($itemProcessedIds) && empty($itemErrorIds)) {
                    $processedIds[] = $id;
                    continue;
                }
            }

            $errorIds[] = $id;
            $failedRecords[] = [
                'id' => $id,
                'cotizacion' => data_get($quote, 'cotizacion'),
                'estado' => data_get($quote, 'estado'),
                'message' => data_get($response, 'message', 'No se pudo actualizar la cotización en SQL Server.'),
            ];
        }

        if (!empty($processedIds)) {
            GpCotizacion::whereIn('id', $processedIds)
                ->update(['import_job' => self::PROCESSED]);
        }

        if (!empty($errorIds)) {
            GpCotizacion::whereIn('id', $errorIds)
                ->update(['import_job' => self::ERROR]);
        }

        $this->logFailedQuoteUpdates($failedRecords, $batchResponse);

        return [
            'success' => empty($errorIds),
            'processed_count' => count($processedIds),
            'error_count' => count($errorIds),
            'failed_records' => $failedRecords,
            'response' => $batchResponse,
        ];
    }

    protected function shouldRetryIndividually(array $response): bool
    {
        $message = mb_strtolower((string) data_get($response, 'message', ''));

        return str_contains($message, 'falló en la posición')
            || str_contains($message, 'fallo en la posicion')
            || str_contains($message, 'filas afectadas: 0');
    }

    protected function failedRecordsFromQuotes(Collection $quotes, array $response): array
    {
        return $quotes->map(function ($quote) use ($response) {
            return [
                'id' => data_get($quote, 'id'),
                'cotizacion' => data_get($quote, 'cotizacion'),
                'estado' => data_get($quote, 'estado'),
                'message' => data_get($response, 'message', 'No se pudo actualizar la cotización en SQL Server.'),
            ];
        })->values()->toArray();
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
        try {
            $response = $this->api->updateAllQuoteStatusSql($payload);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ];
        }

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

    protected function logFailedQuoteUpdates(array $failedRecords, array $batchResponse): void
    {
        if (empty($failedRecords)) {
            return;
        }

        Log::warning('cotizaciones_no_actualizadas_sql_server', [
            'error_count' => count($failedRecords),
            'records' => $failedRecords,
            'batch_message' => data_get($batchResponse, 'message'),
            'batch_status' => data_get($batchResponse, 'status'),
        ]);
    }
}
