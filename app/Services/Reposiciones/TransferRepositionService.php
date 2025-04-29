<?php

namespace App\Services\Reposiciones;

use App\Models\GpArticulo;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\reposicion\GpTrasladosReposicionCabecera;
use App\Services\Api\ApiGpService;
use App\Services\Reposiciones\Contracts\TransferRepositionServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferRepositionService implements TransferRepositionServiceInterface
{
 use JsonResponseTrait;
    protected ApiGpService $apiGp;
    public function __construct(ApiGpService $apiGp)
    {
        $this->apiGp = $apiGp;
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
        return  $this->apiGp->getTransferHeader($params) ?? [];
    }


    public function fetchTransferDetail($numDoc)
    {
        $params = [
            'idCompany' => config('services.api_gp.company'),
            'numDoc' => $numDoc,
        ];
        return  $this->apiGp->getTransferDetail($params) ?? [];
    }


    public function processDocumentsInBatches($request, $transferHeader)
    {

        $processedDocuments = [];
        $documentFilter = $request['numDoc'];
        $batchSize = 100;
        $batch  = [];
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
                'Bodega_Origen'     => $document['origen'],
                'Bodega_Destino'    => $document['destino'],
                'Codigo_Motivo'     => $document['codmotivo'],
                'Motivo'            => $document['motivo'],
                'Fecha_Documento'   => substr($document['fecha'], 0, 10),
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
        $compartmentCache = [];

        // 3. Precargar registros ya existentes
        $existing = GpTrasladosReposicion::where('cabecera_id', $transferHeader->id)
            ->whereIn('ART_CODIGO', $articleCodes)
            ->get()
            ->keyBy('ART_CODIGO');

        $insertData = [];

        foreach ($transferDetails as $detail) {
            $codigo = $detail['item'];
            $origen = $detail['origen'];
            $key = $codigo . '_' . $origen;

            // 4. Obtener artículo
            $article = $articles[$codigo] ?? null;

            if (!$article) {
                Log::warning("Artículo no encontrado: $codigo");
                continue;
            }

            // 5. Obtener compartimento (con cache)
            if (!array_key_exists($key, $compartmentCache)) {
                $compartmentRaw = $this->getArticleCompartment($codigo, $origen);
                $compartmentCache[$key] = $compartmentRaw;
            }

            $compartment = $compartmentCache[$key];

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
                'compartimiento' => $compartment ?? '',
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
        ];
        $compartment = $this->apiGp->getStockByWarehouseAndCompartment($params) ?? [];
        return  $compartment[0]['COMPARTIMIENTO'] ?? '';
    }
}
