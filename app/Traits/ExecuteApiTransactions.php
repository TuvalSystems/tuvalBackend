<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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

    protected function handleDocumentAction(\Closure $accion, string $canalLog, string $contextoLog): SymfonyResponse|JsonResponse
    {
        try {
            return $accion();
        } catch (\Throwable $e) {
            return $this->captureAndFormatException($e, $canalLog, $contextoLog);
        }
    }


    private function captureAndFormatException(\Throwable $e, string $canalLog, string $contextoLog): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse($e->errors(), 'validation error', 422);
        }

        $mensaje = $e->getMessage();
        $codigo = 500;

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            $mensaje = 'Las credenciales no son válidas.';
            $codigo = 401;
        } elseif ($e instanceof HttpException) {
            $codigo = $e->getStatusCode();
        } elseif ($e instanceof ModelNotFoundException) {
            $mensaje = 'El recurso solicitado no fue encontrado.';
            $codigo = 404;
        } elseif ($e instanceof \DomainException) {
            $mensaje = $e->getMessage();
            $codigo = 422;
        } elseif ($e instanceof QueryException) {
            $codigo = ((string) $e->getCode() === '40001') ? 409 : 500;
            $mensaje = ($codigo === 409) ? 'Recurso ocupado, reintente.' : 'Error de integridad en base de datos.';

            $this->logException($canalLog, "Error de Base de Datos en $contextoLog : ", [
                'message' => $e->getMessage(),
                'sql'     => $e->getSql()
            ]);
        } elseif ($e instanceof RuntimeException) {
            $codigo = 409;
            $this->logException($canalLog, "Conflicto de Negocio en $contextoLog : ", [
                'message' => $e->getMessage(),
                'linea'   => $e->getLine(),
                'file'    => $e->getFile()
            ], 'warning');
        } else {
            $this->logException($canalLog, "Error General en $contextoLog : ", [
                'message' => $e->getMessage(),
                'linea'   => $e->getLine(),
                'file'    => $e->getFile()
            ]);
            $mensaje = config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error interno.';
        }

        return $this->buildErrorResponse($mensaje, $codigo);
    }

    private function logException(string $canalLog, string $message, array $context, string $level = 'error'): void
    {
        try {
            $channels = config('logging.channels', []);

            if (array_key_exists($canalLog, $channels)) {
                Log::channel($canalLog)->log($level, $message, $context);
                return;
            }

            Log::log($level, $message, array_merge($context, [
                'requested_log_channel' => $canalLog,
            ]));
        } catch (\Throwable) {
            // El logging no debe reemplazar la respuesta HTTP del error original.
        }
    }
}
