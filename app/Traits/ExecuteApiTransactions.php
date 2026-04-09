<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ExecuteApiTransactions
{

    protected function handleApiAction(\Closure $accion, string $canalLog, string $contextoLog): JsonResponse
    {
        try {
            // Retorno #1: Éxito
            return $accion();
        } catch (\Throwable $e) {
            // Caso Especial: La validación suele tener una estructura diferente (array de errores)
            if ($e instanceof ValidationException) {
                // Retorno #2: Error de Validación
                return $this->validationErrorResponse($e->errors(), 'validation error', 422);
            }

            // Para el resto de excepciones, definimos variables por defecto
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
                // Retorno para errores de lógica de negocio (ej. "Stock insuficiente")
                $mensaje = $e->getMessage();
                $codigo = 422; // O 400 (Bad Request) según prefieras
            } elseif ($e instanceof QueryException) {
                if ($e->getCode() === '40001') {
                    $mensaje = 'El recurso está ocupado temporalmente por otro proceso. Por favor, reintente.';
                    $codigo = 409; // Conflict
                } else {
                    $mensaje = 'Error de integridad procesando los datos en la base.';
                    $codigo = 500;
                }
                // Lógica de Logs para Base de Datos
                Log::channel($canalLog)->error("Error de Base de Datos en $contextoLog : ", [
                    'message' => $e->getMessage(),
                    'sql'     => $e->getSql(),
                    'request' => request()->except(['password', 'password_confirmation', 'token'])
                ]);
            } else {
                // Throwable General (Error por defecto / Fallback)
                Log::channel($canalLog)->error("Error General en $contextoLog : ", [
                    'message' => $e->getMessage(),
                    'linea'   => $e->getLine(),
                    'file'    => $e->getFile(),
                    'trace'   => $e->getTraceAsString(),
                    'request' => request()->except(['password', 'password_confirmation', 'token'])
                ]);
                $mensaje = config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error interno en el servidor.';
                $codigo = 500;
            }

            // Retorno #3: Respuesta de Error Estándar (unificada)
            return $this->buildErrorResponse($mensaje, $codigo);
        }
    }
}
