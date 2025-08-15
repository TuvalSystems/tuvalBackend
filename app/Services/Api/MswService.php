<?php

namespace App\Services\Api;

use CodeDredd\Soap\Facades\Soap;
use Http\Client\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class MswService
{
    /** Aqui van todos los apis de master web que no se han migrado todavia a apigp */


    public function transaccional(string $method, array $params)
    {
        $maxAttempts = 3;
        $attempt = 0;
        do {
            try {
                $attempt++;
                $response = Soap::baseWsdl(env('WEBSERVICES_TRANSACCIONES_URL'))
                    ->$method($params);

                $response->throw();
                return $response->json();
            } catch (RequestException $e) {
                Log::channel('soap_errors')->warning("SOAP RequestException en método [{$method}]: {$e->getMessage()}", [
                    'params' => $params,
                    'response' => optional($e)->body(),
                ]);
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException("Error persistente en el servicio externo ({$method}): " . $e->getMessage(), 0, $e);
                }

                sleep(1);
            } catch (Throwable $e) {
                Log::channel('soap_errors')->error("SOAP Throwable en método [{$method}]: {$e->getMessage()}", [
                    'params' => $params,
                ]);
                throw new \RuntimeException("Error inesperado en el método SOAP ({$method})", 0, $e);
            }
        } while ($attempt >= $maxAttempts);
        throw new \RuntimeException("No se pudo ejecutar el método SOAP '{$method}' tras {$maxAttempts} intentos.");
    }


    public function cobranzas(string $method, array $params)
    {
        $maxAttempts = 3;
        $attempt = 0;
        do {
            try {
                $attempt++;
                $response = Soap::baseWsdl(env('WEBSERVICES_COBRANZAS_URL'))
                    ->$method($params);

                $response->throw();
                return $response->json();
            } catch (RequestException $e) {
                Log::channel('soap_errors')->warning("SOAP RequestException en método [{$method}]: {$e->getMessage()}", [
                    'params' => $params,
                    'response' => optional($e)->body(),
                ]);
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException("Error persistente en el servicio externo ({$method}): " . $e->getMessage(), 0, $e);
                }

                sleep(1);
            } catch (Throwable $e) {
                Log::channel('soap_errors')->error("SOAP Throwable en método [{$method}]: {$e->getMessage()}", [
                    'params' => $params,
                ]);
                throw new \RuntimeException("Error inesperado en el método SOAP ({$method})", 0, $e);
            }
        } while ($attempt >= $maxAttempts);
        throw new \RuntimeException("No se pudo ejecutar el método SOAP '{$method}' tras {$maxAttempts} intentos.");
    }


    public function listaprecios(array $params)
    {
        $maxAttempts = 3;
        $attempt = 0;
        do {
            try {
                $attempt++;
                $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
                    ->MST_Lista_Precio([
                        'Lista_Precio_Articulo' => $params,
                    ]);

                $response->throw();
                return $response->json()['MST_Lista_PrecioResult']['CS_LISTA_PRECIO'];
            } catch (RequestException $e) {
                Log::channel('soap_errors')->warning("SOAP RequestException en método [Lista de Precios]: {$e->getMessage()}", [
                    'params' => $params,
                    'response' => optional($e)->body(),
                ]);
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException("Error persistente en el servicio externo (Lista de Precios): " . $e->getMessage(), 0, $e);
                }

                sleep(1);
            } catch (Throwable $e) {
                Log::channel('soap_errors')->error("SOAP Throwable en método [Lista de Precios]: {$e->getMessage()}", [
                    'params' => $params,
                ]);
                throw new \RuntimeException("Error inesperado en el método SOAP (Lista de Precios)", 0, $e);
            }
        } while ($attempt >= $maxAttempts);
        throw new \RuntimeException("No se pudo ejecutar el método SOAP 'Lista de Precios' tras {$maxAttempts} intentos.");
    }
}
