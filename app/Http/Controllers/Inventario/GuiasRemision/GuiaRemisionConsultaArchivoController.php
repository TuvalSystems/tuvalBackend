<?php

namespace App\Http\Controllers\Inventario\GuiasRemision;

use App\Http\Controllers\Controller;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteMediaServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuiaRemisionConsultaArchivoController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly DeliveryNoteMediaServiceInterface $media) {}

    public function index(Request $request, string $tipo, int $guia): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $tipo, $guia): JsonResponse {
            return $this->successResponse('Archivos de la guía obtenidos.',
                $this->media->getConsultationFiles($tipo, $guia, (int) $request->user()->id), 200);
        }, 'stack', 'GuiaRemisionConsultaArchivoController@index');
    }

    public function show(Request $request, string $tipo, int $guia, int $archivo): Response
    {
        return $this->handleDocumentAction(function () use ($request, $tipo, $guia, $archivo): Response {
            return $this->media->downloadConsultationFile($tipo, $guia, $archivo, (int) $request->user()->id);
        }, 'stack', 'GuiaRemisionConsultaArchivoController@show');
    }
}
