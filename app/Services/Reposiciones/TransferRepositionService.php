<?php

namespace App\Services\Reposiciones;

use App\Http\Controllers\bodegas\IngresosController;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpArticulo;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\reposicion\GpTrasladosReposicionCabecera;
use App\Services\Api\ApiGpService;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferRepositionService implements TransferRepositionServiceInterface
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly ApiGpService $apiGp,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly ApiGpReturnsIntegrationService $apiGpReturnsIntegrationService,
    ) {

    }

    public function validateRequest($request)
    {
        if (empty($request['fecha'])) {
            throw new \InvalidArgumentException('La fecha es requerida');
        }
    }

    public function fectchTransferHeader($request)
    {
        $params = [
            'idCompany' => config('services.api_gp.company'),
            'fecha' => date('Y-m-d', timestamp: strtotime($request->fecha)),
            'numDoc' => $request->numDoc,
        ];
        return $this->apiGp->getTransferHeader($params) ?? [];
    }


    public function fetchTransferDetail($numDoc)
    {
        $params = [
            'idCompany' => config('services.api_gp.company'),
            'numDoc' => $numDoc,
        ];
        return $this->apiGp->getTransferDetail($params) ?? [];
    }


    public function processDocumentsInBatches($request, $transferHeader)
    {

        $processedDocuments = [];
        $documentFilter = $request['numDoc'];
        $batchSize = 100;
        $batch = [];
        $errors = [];

        foreach ($transferHeader as $header) {
            if (empty($documentFilter) || $header['numdoc'] == $documentFilter) {
                $batch[] = $header;
                if (count($batch) >= $batchSize) {
                    $result = $this->processBatch($batch);
                    $processedDocuments = array_merge($processedDocuments, $result['processed']);
                    $errors = array_merge($errors, $result['errors']);
                    $batch = [];
                }
            }
        }
        if (!empty($batch)) {
            $result = $this->processBatch($batch);
            $processedDocuments = array_merge($processedDocuments, $result['processed']);
            $errors = array_merge($errors, $result['errors']);
        }
        return [
            'processed' => $processedDocuments,
            'errors' => $errors
        ];
    }

    protected function processBatch(array $batch): array
    {
        $results = [];
        $errors = [];
        foreach ($batch as $header) {
            try {
                $results[] = $this->processIndividualReplacement($header);
            } catch (\Exception $e) {
                // Registrar el error pero continuar con los demás
                Log::error("Error procesando documento {$header['numdoc']}: " . $e->getMessage());
                $errors[] = [
                    'numdoc' => $header['numdoc'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'processed' => $results,
            'errors' => $errors
        ];
    }


    public function buildResponse($processedDocuments)
    {
        if (empty($processedDocuments['processed']) && empty($processedDocuments['errors'])) {
            return $this->errorResponse('No se encontraron documentos de transferencia', null, 404);
        }
        if (!empty($processedDocuments['errors'])) {
            return $this->errorResponse(
                'Se encontraron errores al procesar los documentos de transferencia',
                $processedDocuments['errors'],
                422
            );
        }

        if (empty($processedDocuments)) {
            return $this->errorResponse('No se encontraron documentos de transferencia', null, 404);
        }
        return $this->successResponse(
            'Reposicion guardada',
            $processedDocuments['processed'],
            200
        );
    }


    public function processIndividualReplacement($document)
    {
        $transferDetail = $this->fetchTransferDetail($document['numdoc']);
        if (empty($transferDetail)) {
            throw new \RuntimeException('No existen detalles de la transferencia');
        }

        $transferHeader = $this->updateOrCreateTransferHeader($document);
        $this->processTransferDetailsInBatches($transferDetail, $transferHeader);
        return $transferHeader->Documento;
    }

    public function updateOrCreateTransferHeader($document)
    {
        return GpTrasladosReposicionCabecera::updateOrCreate(
            ['Documento' => $document['numdoc']],
            [
                'Bodega_Origen' => $document['origen'],
                'Bodega_Destino' => $document['destino'],
                'Codigo_Motivo' => $document['codmotivo'],
                'Motivo' => $document['motivo'],
                'Fecha_Documento' => substr($document['fecha'], 0, 10),
            ]
        );
    }



    public function processTransferDetailsInBatches($transferDetail, $transferHeader)
    {
        $batchSize = 40;
        $chunks = array_chunk($transferDetail, $batchSize);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                // ✅ Nuevo método optimizado, sin loops internos aquí
                $this->bulkUpdateOrInsertTransferDetails($transferHeader, $chunk);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error procesando lote de detalles optimizado: " . $e->getMessage());
                throw $e;
            }
        }
    }


    public function bulkUpdateOrInsertTransferDetails($transferHeader, array $transferDetails)
    {
        $articleCodes = array_column($transferDetails, 'item');

        // 1. Precargar artículos
        $articles = GpArticulo::whereIn('ART_CODIGO', $articleCodes)
            ->get()
            ->keyBy('ART_CODIGO');

        // 2. Cachear compartimentos únicos por item+origen
        // $compartmentCache = [];

        // 3. Precargar registros ya existentes
        $existing = GpTrasladosReposicion::where('cabecera_id', $transferHeader->id)
            ->whereIn('ART_CODIGO', $articleCodes)
            ->get()
            ->keyBy('ART_CODIGO');

        $insertData = [];

        foreach ($transferDetails as $detail) {
            $codigo = $detail['item'];
            $origen = $detail['origen'];
            //$key = $codigo . '_' . $origen;

            // 4. Obtener artículo
            $article = $articles[$codigo] ?? null;

            if (!$article) {
                Log::warning("Artículo no encontrado: $codigo");
                continue;
            }

            // 5. Obtener compartimento (con cache)
            // if (!array_key_exists($key, $compartmentCache)) {
            //     $compartmentRaw = $this->getArticleCompartment($codigo, $origen);
            //     $compartmentCache[$key] = $compartmentRaw;
            // }

            // $compartment = $compartmentCache[$key];

            // 6. Armar datos
            $data = [
                'Documento' => $transferHeader->Documento,
                'Codigo_Motivo' => $transferHeader->Codigo_Motivo,
                'ART_DESCRIPCION' => $article['ART_DESCRIPCION'],
                'ART_CODIGO_TRES_LETRAS' => $article['ART_CODIGO_TRES_LETRAS'],
                'ART_PLAN_UMEDIDA' => $article['ART_PLAN_UMEDIDA'],
                'ART_PESO' => $article['ART_PESO'],
                'cantidad' => $detail['cantidad'],
                'bodega_origen' => $transferHeader->Bodega_Origen,
                'bodega_destino' => $transferHeader->Bodega_Destino,
                'compartimiento' => $detail['trd_ubic'] ?? '', // $compartment['compartimento'], ya me devuelve en el detalle
                // 'compartimiento' => $compartment ?? '',
                'Fecha_Documento' => $transferHeader->Fecha_Documento,
                'updated_at' => now()
            ];


            if ($existing->has($codigo)) {
                // Update si existe
                GpTrasladosReposicion::where('cabecera_id', $transferHeader->id)
                    ->where('ART_CODIGO', $codigo)
                    ->update($data);
            } else {
                // Insert nuevo
                $data['cabecera_id'] = $transferHeader->id;
                $data['ART_CODIGO'] = $codigo;
                $data['created_at'] = now();
                $insertData[] = $data;
            }
        }

        if (!empty($insertData)) {
            GpTrasladosReposicion::insert($insertData);
        }
    }


    public function getArticleCompartment($articleCode, $warehouse)
    {
        $params = [
            'idCompany' => config('services.api_gp.company'),
            'articulo' => $articleCode,
            'bodega' => $warehouse,
            'compartimento' => '',
        ];
        // MST_Stock_Articulo_Bodega_CompartimientoResult
        $response = $this->stockWarehouseService->fetchStockWarehouseCompartiment($params);
        return $response['COMPARTIMIENTO'] ?? null;
    }


    public function getDetailReceivedReposition($guideId)
    {
        $guia = GpGuiaRemision::find($guideId);
        $detalle = GpGuiaRemisionDetalle::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO')
            ->where('guia_remision_id', $guia->id)
            ->select('gp_guia_remision_detalles.*', 'gp_articulos.ART_DECIMAL_CANTIDAD')
            ->get();
        $items = collect($detalle)
            ->pluck('GUI_REM_CODI_INTERNO')
            ->toArray();

        $responseCompartimentos = $this->apiGpReturnsIntegrationService->consultMultipleItemsBin($items, $guia->bodega_destino);
        $response = collect($detalle)->map(function ($item) use ($responseCompartimentos) {
            $compartimento = collect($responseCompartimentos)
                ->first(function ($response) use ($item) {
                    // 🔍 Verificar dentro de 'data' si existe el item
                    $codigo = data_get($response, 'data.Item');
                    return $codigo && $codigo === $item['GUI_REM_CODI_INTERNO'];
                });
            $lista = data_get($compartimento, 'lista', []);
            // $compart = data_get($compartimento, 'data', []) ?? [];
            $item->pendiente = number_format(round($item->GUI_REM_CANTIDAD - $item->recibido, 2), 2, '.', '');
            $item->recibido = $item->pendiente;
            $item->compartimientos = $lista;
            // $item->compartimiento = $compart['bin'] ?? null;
            $item->compartimiento = null;

            return $item;
        })->values()->all();

        return $response;
    }


    /** proceso de recibir reposicion web * */

    public function processReceivedRepositionWeb($guideId)
    {
        $guia = GpGuiaRemision::find($guideId);
        $detalle = $this->getDataDetailReceivedReposition($guia);
        //   return $detalle;
        $traslados = $this->receivedGuideRepositionWeb($guia, $detalle);
        return $traslados;
    }


    public function getDataDetailReceivedReposition($guia)
    {
        $detalle = GpGuiaRemisionDetalle::join('gp_articulos', 'gp_articulos.ART_CODIGO', 'gp_guia_remision_detalles.GUI_REM_CODI_INTERNO')
            ->where('guia_remision_id', $guia->id)
            ->select('gp_guia_remision_detalles.*', 'gp_articulos.ART_DECIMAL_CANTIDAD')
            ->get();
        $items = $detalle->pluck('GUI_REM_CODI_INTERNO')->all();
        $resp = $this->apiGpReturnsIntegrationService->consultMultipleItemsBin($items, $guia->bodega_destino);

        $binsByItem = [];
        foreach ($resp as $r) {
            $itemCode = (string) data_get($r, 'data.Item', '');
            if ($itemCode === '' || isset($binsByItem[$itemCode])) {
                continue;
            }
            $bin = trim((string) data_get($r, 'lista.0.bin', ''));
            $binsByItem[$itemCode] = $bin === '' ? 'GENERAL' : $bin;
        }
        // return $binsByItem;
        $response = [];

        foreach ($detalle as $item) {
            $decimalPlaces = (int) $item->ART_DECIMAL_CANTIDAD;
            $pendiente = round((float) $item->GUI_REM_CANTIDAD - (float) $item->recibido, $decimalPlaces);
            if ($pendiente < 0) {
                $pendiente = 0.0;
            }
            $item->pendiente = round($pendiente, 2);
            $item->recibido = round($item->pendiente, 2);
            $bin = $binsByItem[$item->GUI_REM_CODI_INTERNO] ?? 'GENERAL';
            $item->compartimiento = $bin;
            $response[] = $item;
        }
        return $response;
    }


    public function receivedGuideRepositionWeb($guia, $detalle)
    {
        $itemsInput = collect($detalle)
            ->filter(fn($i) => ($i['recibido'] ?? 0) > 0)
            ->values();
        if ($itemsInput->isEmpty()) {
            throw new \InvalidArgumentException('No hay items para procesar en la reposición');
        }

        $trasladoIds = $itemsInput->pluck('detalle_id')->unique()->all();
        $traslados = GpTrasladosReposicion::query()
            ->whereIn('id', $trasladoIds)
            ->get(['id', 'Documento'])
            ->keyBy('id'); // id => traslado
        $pedidos = $traslados->pluck('Documento')->unique()->all();
        $transferencias = GpGuiaRemisionTransferencia::query()
            ->where('guia_remision_id', $guia->id)
            ->whereIn('pedido', $pedidos)
            ->get(['pedido', 'codigo'])
            ->keyBy('pedido'); // pedido => transferencia

        $documentos = [];

        foreach ($itemsInput as $it) {
            $tras = $traslados->get($it['detalle_id']);
            if (!$tras) {
                continue;
            }
            $doc = $tras->Documento;
            $codigoTransfer = optional($transferencias->get($doc))->codigo;
            if (!isset($documentos[$doc])) {
                $documentos[$doc] = [
                    'Documento' => $doc,
                    'transferencia' => $codigoTransfer,
                    'items' => [],
                ];
            }

            $documentos[$doc]['items'][] = [
                'id' => $it['id'],
                'Item' => $it['GUI_REM_CODI_INTERNO'],
                'Compartimento' => $it['compartimiento'] ?: 'GENERAL',
                'Cantidad' => (float) $it['recibido'], // numérico
            ];
        }
        $ws = app(WebserviceController::class);

        foreach ($documentos as $doc => $pack) {
            $itemsWs = [];
            foreach ($pack['items'] as $it) {
                if ($it['Cantidad'] > 0) {
                    $itemsWs[] = [
                        'Item' => $it['Item'],
                        'Compartimento' => $it['Compartimento'],
                        'Cantidad' => $it['Cantidad'],
                    ];
                }
            }
            if (empty($itemsWs)) {
                continue;
            }
            $params = [
                'IDCompania' => env('ID_COMPANIA'),
                'Documento' => $pack['Documento'],
                'NumeroTransferOri' => $pack['transferencia'],
                'BodegaOrigen' => $guia['bodega_origen'],
                'BodegaDestino' => $guia['bodega_destino'],
                'Observacion' => $guia['GUI_REM_OBSERVACION'],
                'Usuario' => 'adminWeb',//$user->USERID ,
                'Items' => array_values($itemsWs),
            ];

            $resp = $ws->inventario('GeneraRecepcionTransferenciaReposicon', $params);
            $ret = data_get($resp, 'GeneraRecepcionTransferenciaReposiconResult.Retorno.0');
            Log::info('Respuesta WS Recepción Reposición Web', [
                'documento' => $pack['Documento'],
                'response' => $resp,
            ]);
            if (!isset($ret['Codigo']) || (string) $ret['Codigo'] !== '0') {
                throw new \RuntimeException('Error al procesar la recepción de la reposición para el documento ' . $pack['Documento'] . ': ' . ($ret['Mensaje'] ?? 'Error desconocido'));
            }
            $ids = collect($pack['items'])->pluck('id')->all();
            $case = [];
            $idList = [];

            foreach ($pack['items'] as $it) {
                $id = (int) $it['id'];
                $val = (float) $it['Cantidad'];
                if ($val <= 0)
                    continue;

                $idList[] = $id;
                // recibido = recibido + X (seguro contra inyección: casteado y sin comillas)
                $case[] = "WHEN {$id} THEN {$val}";
            }

            if ($idList && $case) {
                $idIn = implode(',', $idList);
                $caseSql = implode(' ', $case);

                // Usa transacción local solo para las escrituras en MySQL
                DB::transaction(function () use ($idIn, $caseSql) {
                    DB::statement("
                    UPDATE gp_guia_remision_detalles
                     SET recibido = COALESCE(recibido, 0) + CASE id {$caseSql} ELSE 0 END
                    WHERE id IN ({$idIn})
                ");
                });
            }
        }
        $guia = GpGuiaRemision::find($guia->id);
        $listo = $this->checkguiacompleta($guia);
        $data = $guia->toArray();

        // Idealmente: después de commit
        (new IngresosController())->ingreso('traslado_reposicion', $data, $detalle);

        if ($listo) {
            $guia->estado = 'recibida';
            $guia->save();
        }
        return $guia->refresh();
    }


    public function checkguiacompleta($guia): bool
    {
        $epsilon = 0.0001;

        // Si tus cantidades están en DECIMAL en BD, esto será muy preciso;
        // si son FLOAT, el epsilon ayuda.
        $hayPendientes = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)
            ->whereRaw('COALESCE(recibido,0) + ? < GUI_REM_CANTIDAD', [$epsilon])
            ->exists();

        return !$hayPendientes;
    }

}
