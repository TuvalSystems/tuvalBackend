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
            'error' => $message,
            'errors' => $errors,
        ], $code);
    }


    protected function buildErrorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message, 'errors' => []], $statusCode);
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
        $filenameIn = (string) ($result['filename'] ?? ('document-' . now()->format('Ymd-His') . '.pdf'));
        $contentType = (string) ($result['content_type'] ?? 'application/pdf');

        // Normaliza extensión
        $filenameIn = Str::finish(Str::replaceLast('.pdf', '', $filenameIn), '.pdf');

        // Fallback ASCII seguro (por si el filename tiene tildes/emoji)
        $asciiBase = Str::ascii(pathinfo($filenameIn, PATHINFO_FILENAME));
        $asciiBase = preg_replace('/[^\w.-]+/u', '-', $asciiBase) ?: 'document';
        $filenameAsc = $asciiBase . '.pdf';

        // Disposición robusta (adjunto) con filename y filename*
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filenameIn,     // nombre preferido (UTF-8)
            $filenameAsc     // fallback ASCII
        );

        return response($binary, $status, [
            'Content-Type' => $contentType,                  // application/pdf
            'Content-Disposition' => $disposition,                  // incluye filename y filename*
            'Content-Length' => strlen($binary),               // byte length
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',

            // 👇 Permite que Axios/Fetch lean Content-Disposition en el navegador
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',

            // 👇 Header alterno por si quieres leer nombre sin parsear Content-Disposition
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
