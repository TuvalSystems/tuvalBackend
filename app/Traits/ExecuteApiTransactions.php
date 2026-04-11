<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ExecuteApiTransactions
{

    protected function handleApiAction(\Closure $accion, string $canalLog, string $contextoLog): JsonResponse
    {
        try {
            return $accion();
        } catch (\Throwable $e) {
           return $this->captureAndFormatException($e, $canalLog, $contextoLog);
        }
    }

    protected function handlePdfAction(\Closure $accion, string $canalLog, string $contextoLog): Response
    {
        try {
            return $accion();
        } catch (\Throwable $e) {
            return $this->captureAndFormatException($e, $canalLog, $contextoLog);
        }
    }


    private function captureAndFormatException(\Throwable $e, string $canalLog, string $contextoLog): Response
    {
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse($e->errors(), 'validation error', 422);
        }

        $mensaje = $e->getMessage();
        $codigo = 500;

        if ($e instanceof RuntimeException) {
            $codigo = 409;
        } elseif ($e instanceof HttpException) {
            $codigo = $e->getStatusCode();
        } elseif ($e instanceof ModelNotFoundException) {
            $mensaje = 'El recurso solicitado no fue encontrado.';
            $codigo = 404;
        } elseif ($e instanceof \DomainException) {
            $mensaje = $e->getMessage();
            $codigo = 422;
        } elseif ($e instanceof QueryException) {
            $codigo = ($e->getCode() === '40001') ? 409 : 500;
            $mensaje = ($codigo === 409) ? 'Recurso ocupado, reintente.' : 'Error de integridad en base de datos.';

            Log::channel($canalLog)->error("Error de Base de Datos en $contextoLog : ", [
                'message' => $e->getMessage(),
                'sql'     => $e->getSql()
            ]);
        } else {
            Log::channel($canalLog)->error("Error General en $contextoLog : ", [
                'message' => $e->getMessage(),
                'linea'   => $e->getLine(),
                'file'    => $e->getFile()
            ]);
            $mensaje = config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error interno.';
        }

        return $this->buildErrorResponse($mensaje, $codigo);
    }
}
