<?php

namespace App\Traits;
use Illuminate\Http\JsonResponse;

trait JsonResponseTrait
{
    protected function successResponse($message , $data = [], int $code ): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }


    protected function errorResponse($message, $errors = null,  int $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }


    protected function buildErrorResponse(string $message, int $statusCode ): JsonResponse
    {
        return response()->json([  'success' => false,  'error' => $message], $statusCode);
    }

    protected function buildSuccessResponse(string $message, int $statusCode ): JsonResponse
    {
        return response()->json(['success'=> true,'message'=> $message], $statusCode);
    }
}
