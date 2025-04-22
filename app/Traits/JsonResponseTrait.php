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


    public function errorResponse($message, $errors = null,  int $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }


    private function buildErrorResponse(string $message, int $statusCode ): JsonResponse
    {
        return response()->json([  'success' => false,  'error' => $message], $statusCode);
    }

    public function buildSuccessResponse(string $message, int $statusCode ): JsonResponse
    {
        return response()->json(['success'=> true,'message'=> $message], $statusCode);
    }
}
