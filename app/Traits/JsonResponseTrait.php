<?php

namespace App\Traits;

use Illuminate\Http\Response;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;

trait JsonResponseTrait
{
    protected function successResponse($message, $data = [], int $code): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }


    protected function errorResponse($message, $errors = null, int $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }


    protected function buildErrorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message], $statusCode);
    }

    protected function buildSuccessResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], $statusCode);
    }


    /** para respuestas de JSon true o false de existencias de algo  */

    protected function buildExistResponse(bool $exists, string $message, int $statusCode): JsonResponse
    {
        return response()->json(['exists' => $exists, 'message' => $message], $statusCode);
    }


    protected function buildPdfResponse(array $result, $status = 200): Response|ResponseFactory
    {
        $binary = (string) ($result['binary'] ?? '');
        $filename = (string) ($result['filename'] ?? ('document-' . now()->format('Ymd-His') . '.pdf'));
        $contentType = (string) ($result['content_type'] ?? 'application/pdf');

        return response($binary, $status, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($binary),   // opcional, ayuda a algunos navegadores
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
