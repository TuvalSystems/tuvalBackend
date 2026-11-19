<?php

namespace App\Services\Facturacion;

class InvoiceGpResponse
{
    public static function invoiceNumber(mixed $response): string
    {
        return data_get($response, 'success') === true
            ? trim((string) data_get($response, 'data.numeroDocGenerado', '')) : '';
    }

    public static function isMissing(mixed $response): bool
    {
        // Contrato actual del DAO de API-GP. No confundir cualquier HTTP 500 con ausencia.
        return data_get($response, 'success') === false
            && (int) data_get($response, 'status') === 500
            && data_get($response, 'message') === 'Error al ejecutar el SP de detalle de factura: No existe registro para los parámetros proporcionados';
    }
}
