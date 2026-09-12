<?php

namespace App\Traits;

use Illuminate\Http\Response;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Symfony\Component\HttpFoundation\HeaderUtils;

trait JsonResponseTrait
{
    protected function successResponse(string $message, $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }


    protected function errorResponse(string $message, $errors = null, int $code = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'errors' => $errors,
        ], $code);
    }


    protected function buildErrorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message, 'errors' => []], $statusCode);
    }

    protected function buildSuccessResponse(string $message, ?int $code = 200 ): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], $code);
    }


    /** para respuestas de JSon true o false de existencias de algo  */

    protected function buildExistResponse(bool $exists, string $message, int $statusCode = 200): JsonResponse
    {
        return response()->json(['exists' => $exists, 'message' => $message], $statusCode);
    }

    protected function buildFileResponse(array $result, $status = 200): Response|ResponseFactory
    {
        $binary = (string) ($result['binary'] ?? '');

        $filenameIn = (string) (
            $result['filename']
            ?? ('document-' . now()->format('Ymd-His') . '.bin')
        );

        $contentType = (string) (
            $result['content_type']
            ?? 'application/octet-stream'
        );

        /*
     * Obtiene la extensión del nombre recibido.
     * Si no existe, intenta determinarla por el Content-Type.
     */
        $extension = strtolower(
            pathinfo($filenameIn, PATHINFO_EXTENSION)
        );

        if ($extension === '') {
            $extension = match ($contentType) {
                'application/pdf' => 'pdf',

                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                => 'xlsx',

                'application/vnd.ms-excel'
                => 'xls',

                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                => 'docx',

                'application/msword'
                => 'doc',

                'text/csv'
                => 'csv',

                'application/zip'
                => 'zip',

                'text/plain'
                => 'txt',

                default => 'bin',
            };

            $filenameIn .= '.' . $extension;
        }

        /*
     * Nombre ASCII seguro para navegadores que no soporten
     * correctamente nombres UTF-8.
     */
        $baseName = pathinfo($filenameIn, PATHINFO_FILENAME);

        $asciiBase = Str::ascii($baseName);

        $asciiBase = preg_replace(
            '/[^\w.-]+/u',
            '-',
            $asciiBase
        ) ?: 'document';

        $filenameAsc = $asciiBase . '.' . $extension;

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filenameIn,
            $filenameAsc
        );

        return response($binary, $status, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($binary),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
            'X-Filename' => $filenameIn,
        ]);
    }



    /** para respuestas estandar para errores de validaciones */

    protected function validationErrorResponse($errors, string $message = 'validation errors', int $code = 422): JsonResponse
    {

        if ($errors instanceof ValidatorContract) {
            $errors = $errors->errors();
        }
        if ($errors instanceof ViewErrorBag) {
            $errors = $errors->getBag('default');
        }
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
