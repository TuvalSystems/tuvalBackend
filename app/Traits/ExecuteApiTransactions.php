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
        } catch (ModelNotFoundException $mnf) {
            return $this->buildErrorResponse('El recurso solicitado no fue encontrado.', 404);
        } catch (QueryException $qe) {
            Log::channel($canalLog)->error("Error de Base de Datos en $contextoLog : ", [
                'message'   => $qe->getMessage(),
                'sql'       => $qe->getSql(), // Te dirá exactamente qué query falló
                'request'   => request()->except(['password', 'password_confirmation', 'token']) // MEJORA 4
            ]);
            return $this->buildErrorResponse('Error de integridad procesando los datos en la base.', 500);
        } catch (\Throwable $e) {
           Log::channel($canalLog)->error("Error General en $contextoLog : ", [
                'message'   => $e->getMessage(),
                'linea'     => $e->getLine(),
                'file'      => $e->getFile(),
                'trace'     => $e->getTraceAsString(),
                'request'   => request()->except(['password', 'password_confirmation', 'token'])
            ]);
            $mensajeError = config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error interno en el servidor.';
            return $this->buildErrorResponse($mensajeError, 500);
        }
    }
}
