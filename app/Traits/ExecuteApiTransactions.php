<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ExecuteApiTransacctions
{

    protected function executeTransaction(\Closure $accion, string $canalLog, string $contextoLog): JsonResponse
    {
        try {
            return $accion();
        } catch (RuntimeException $r) {
            return $this->buildErrorResponse($r->getMessage(), 409); // Asume que el Trait o el Controller tienen este método
        } catch (ValidationException $ve) {
            return $this->validationErrorResponse($ve->errors(), 'validation error', 422);
        } catch (HttpException $he) {
            return $this->buildErrorResponse($he->getMessage(), $he->getStatusCode());
        } catch (\Throwable $e) {
            Log::channel($canalLog)->error("Error en $contextoLog : ", [
                'message'   => $e->getMessage(),
                'linea'     => $e->getLine(),
                'file'      => $e->getFile(),
                'trace'     => $e->getTraceAsString(),
                'request'   => request()->all()
            ]);

            $mensajeError = config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error interno en el servidor.';
            return $this->buildErrorResponse($mensajeError, 500);
        }
    }
}
