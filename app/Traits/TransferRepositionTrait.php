<?php

namespace App\Traits;

use App\Models\GpArticulo;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\reposicion\GpTrasladosReposicionCabecera;
use App\Services\ApiGpService;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait TransferRepositionTrait
{
    use JsonResponseTrait;
    protected $apiGp;
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
            'fecha' => date('Y-m-d', strtotime($request->fecha)),
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
        $batchSize = 10;
        $batch  = [];

        foreach ($transferHeader as $header) {
            if (empty($documentFilter) || $header['numdoc'] == $documentFilter) {
                $batch[] = $header;
                if (count($batch) >= $batchSize) {
                    $processedDocuments = array_merge($processedDocuments, $this->processBatch($batch));
                    $batch = [];
                }
            }
        }
        if (!empty($batch)) {
            $processedDocuments = array_merge($processedDocuments, $this->processBatch($batch));
        }
        return $processedDocuments;
    }

    protected function processBatch(array $batch): array
    {
        $results = [];

        foreach ($batch as $header) {
            try {
                $results[] = $this->processIndividualReplacement($header);
            } catch (\Exception $e) {
                // Registrar el error pero continuar con los demás
                Log::error("Error procesando documento {$header['numdoc']}: " . $e->getMessage());
            }
        }

        return $results;
    }


    protected function buildResponse($processedDocuments)
    {
        if (empty($processedDocuments)) {
            return $this->errorResponse('No se encontraron documentos de transferencia', null ,404);
        }
        return $this->successResponse(
            'Reposición guardada',
            $processedDocuments,
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
        $batchSize = 10;
        $chunks = array_chunk($transferDetail, $batchSize);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                foreach ($chunk as $detail) {
                    $article = $this->findArticleOrFail($detail['item']);
                    $compartment = $this->getArticleCompartment($detail['item'], $detail['origen']);
                    $this->updateOrCreateTransferDetail($transferHeader, $detail, $article, $compartment);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error procesando lote de detalles: " . $e->getMessage());
                throw $e;
            }
        }
    }



    public function findArticleOrFail($item)
    {
        $article = GpArticulo::where('ART_CODIGO', $item)->first();
        if (!$article) {
            throw new \RuntimeException("El item $item no se encuentra registrado en la web");
        }
        return $article->toArray();
    }


    public function getArticleCompartment($articleCode, $warehouse)
    {
        $params = [
            'idCompany' => config('services.api_gp.company'),
            'articulo' => $articleCode,
            'bodega' => $warehouse,
        ];
        $compartment = $this->apiGp->getStockByWarehouseAndCompartment($params) ?? [];
        return  $compartment[0]['COMPARTIMIENTO'] ?? [];
    }

    public function updateOrCreateTransferDetail($transferHeader, $detail, $article, $compartment)
    {

        $compartment = $compartment['COMPARTIMIENTO'] ?? '';
        GpTrasladosReposicion::updateOrCreate(
            [
                'cabecera_id' => $transferHeader->id,
                'ART_CODIGO' => $article['ART_CODIGO']
            ],
            [
                'Documento' => $transferHeader->Documento,
                'Codigo_Motivo' => $transferHeader->Codigo_Motivo,
                'ART_DESCRIPCION' => $article['ART_DESCRIPCION'],
                'ART_CODIGO_TRES_LETRAS' => $article['ART_CODIGO_TRES_LETRAS'],
                'ART_PLAN_UMEDIDA' => $article['ART_PLAN_UMEDIDA'],
                'ART_PESO' => $article['ART_PESO'],
                'cantidad' => $detail['cantidad'],
                'bodega_origen' => $transferHeader->Bodega_Origen,
                'bodega_destino' => $transferHeader->Bodega_Destino,
                'compartimiento' => $compartment,
                'Fecha_Documento' => $transferHeader->Fecha_Documento,
            ]
        );
    }
}
