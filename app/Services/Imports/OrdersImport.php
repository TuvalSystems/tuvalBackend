<?php

namespace App\Services\Imports;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Services\Api\ApiGpService;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Carbon\CarbonPeriod;

class OrdersImport implements OrdersImportInterface
{
   public function __construct(protected readonly ApiGpService $api) {}

    public function updateDataSqlServerOrdersAndDetail(string $fechaIni, string $fechaFin)
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        // Desactivamos logs
        DB::disableQueryLog();

        $period = CarbonPeriod::create($fechaIni, $fechaFin);
        $totalCabeceras = 0;
        $totalDetalles = 0;

        Log::info("=== INICIO PROCESO (MODO RESILIENTE): {$fechaIni} a {$fechaFin} ===");

        foreach ($period as $date) {
            $diaActual = $date->format('Y-m-d');

            $resultadoDia = $this->procesarDia($diaActual);

            $totalCabeceras += $resultadoDia['cabeceras'];
            $totalDetalles += $resultadoDia['detalles'];

            // Descanso largo entre días
            sleep(2);
            gc_collect_cycles();
        }

        return [
            'cabeceras_procesadas' => $totalCabeceras,
            'detalles_procesados'  => $totalDetalles
        ];
    }

    protected function procesarDia(string $fecha)
    {
        Log::info("Procesando día: {$fecha}");

        // Intentamos traer los datos de la API (Si falla aquí, es error de API, no de DB)
        try {
            $ordersSql = $this->getAllOrdersSqlPendingUpdate($fecha, $fecha);
        } catch (\Exception $e) {
            Log::error("Error API en fecha $fecha: " . $e->getMessage());
            return ['cabeceras' => 0, 'detalles' => 0];
        }

        if (empty($ordersSql)) {
            return ['cabeceras' => 0, 'detalles' => 0];
        }

        $mapaSqlHeaders = collect($ordersSql)->keyBy(fn($item) => trim($item['docOrigen']));
        $keyOrders = $mapaSqlHeaders->keys()->toArray();

        $countCabeceras = 0;
        $countDetalles = 0;

        // Mantenemos lote pequeño (20)
        $chunks = array_chunk($keyOrders, 20);

        foreach ($chunks as $index => $lotePedidosIds) {

            $procesado = false;
            $intentos = 0;
            $maxIntentos = 5; // Intentaremos 5 veces procesar ESTE lote antes de rendirnos

            while (!$procesado && $intentos < $maxIntentos) {
                try {
                    // 1. Aseguramos conexión fresca
                    // Si no es el primer intento, forzamos reconexión
                    if ($intentos > 0) {
                        DB::connection()->disconnect(); // Cortar cable
                        sleep(5); // Esperar castigo del firewall (5 segundos)
                        DB::connection()->reconnect(); // Conectar de nuevo
                    }

                    // --- INICIO OPERACIÓN BASE DE DATOS ---

                    // A. Cabeceras
                    $ordersWeb = GpPedido::whereIn('pedido', $lotePedidosIds)->get();

                    $payloadCabeceras = [];
                    foreach ($ordersWeb as $pedidoWeb) {
                        $key = trim($pedidoWeb->pedido);
                        if ($mapaSqlHeaders->has($key)) {
                            $dataSql = $mapaSqlHeaders->get($key);
                            $payloadCabeceras[] = [
                                'dexRowId'       => $dataSql['dexRowId'],
                                'pedido'         => $pedidoWeb->pedido,
                                'userIdCreator'  => $pedidoWeb->USERID ?? 'adminWeb',
                                'condIdOriginal' => $pedidoWeb->COND_ID_ORIGINAL,
                            ];
                        }
                    }

                    if (!empty($payloadCabeceras)) {
                        $this->api->updateAllOrdersPendingSql($payloadCabeceras);
                        $countCabeceras += count($payloadCabeceras);
                    }

                    // B. Detalles
                    $detailsSql = $this->getAllOrdersDetailSqlPendingUpdate($lotePedidosIds);

                    if (!empty($detailsSql)) {
                        $mapaSqlDetails = $this->extractOrdersDetails($detailsSql);

                        $detailOrdersWeb = GpPedidoDetalle::whereIn('pedido_id', $ordersWeb->pluck('id'))
                            ->with(['pedido:id,pedido'])
                            ->get();

                        $payloadDetalles = [];
                        foreach ($detailOrdersWeb as $detalleWeb) {
                            if (!$detalleWeb->pedido) continue;

                            $docWeb   = strtoupper(trim($detalleWeb->pedido->pedido));
                            $artWeb   = strtoupper(trim($detalleWeb->ART_CODIGO));
                            $lineaWeb = (int) $detalleWeb->orden;
                            $llaveWeb = $docWeb . '-' . $artWeb . '-' . $lineaWeb;

                            if ($mapaSqlDetails->has($llaveWeb)) {
                                $datoSql = $mapaSqlDetails->get($llaveWeb);
                                $payloadDetalles[] = [
                                    'dexRowId'           => $datoSql['dexRowId'],
                                    'bodega'             => $datoSql['bodega'],
                                    'artWeb'             => $detalleWeb->ART_CODIGO,
                                    'artCodigo'          => $datoSql['artCodigo'],
                                    'descripcionOrginal' => $detalleWeb->ART_DESCRIPCION_ORIGINAL,
                                    'valorOrginal'       => $detalleWeb->ART_VALOR_ORIGINAL,
                                    'descuentoOrginal'   => $detalleWeb->descuento_original,
                                    'docGenerado'        => $datoSql['docOrigen']
                                ];
                            }
                        }

                        if (!empty($payloadDetalles)) {
                            foreach (array_chunk($payloadDetalles, 20) as $loteEnvio) {
                                $this->api->updateAllOrdersDetailPendingSql($loteEnvio);
                            }
                            $countDetalles += count($payloadDetalles);
                        }
                    }

                    // Si llegamos aquí, todo salió bien
                    $procesado = true;

                    // Pausa de éxito (para no acelerarnos)
                    usleep(500000); // 0.5 segundos

                } catch (\Exception $e) {
                    $intentos++;
                    Log::warning("Fallo en lote {$index} (Intento {$intentos}/{$maxIntentos}). Esperando para reintentar... Error: " . $e->getMessage());

                    // Si falla, el 'while' volverá a ejecutarse.
                    // La magia está en el 'if ($intentos > 0)' al inicio del while
                    // que desconectará y esperará 5 segundos.
                }
            }
        }

        return ['cabeceras' => $countCabeceras, 'detalles' => $countDetalles];
    }

    // --- Helpers ---

    protected function getAllOrdersSqlPendingUpdate(string $fechaIni, string $fechaFin)
    {
        $resp = $this->api->fetchAllOrdersPendingSql([
            'fechaInicio' => $fechaIni,
            'fechaFin' => $fechaFin
        ]);
        return data_get($resp, 'data', []);
    }

    protected function getAllOrdersDetailSqlPendingUpdate(array $keyOrders)
    {
        $resp = $this->api->fetchAllOrdersDetailPendingSql([
            'pedidos' => $keyOrders
        ]);
        return data_get($resp, 'data', []);
    }

    protected function extractOrderIds($ordersSql): array
    {
        return collect($ordersSql)->pluck('docOrigen')->map(fn($id) => trim($id))->toArray();
    }

    protected function extractOrdersDetails($detailsSql)
    {
        return collect($detailsSql)->mapWithKeys(function ($item) {
            $doc = strtoupper(trim($item['docOrigen']));
            $art = strtoupper(trim($item['artCodigo']));
            $linea = isset($item['linea']) ? (int)$item['linea'] : 0;
            $key = $doc . '-' . $art . '-' . $linea;
            return [$key => $item];
        });
    }
}
