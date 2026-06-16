<?php

namespace App\Http\Controllers\Borradores;

use App\Http\Controllers\Controller;
use App\Services\Venta\Contracts\QuoteDraftServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotizacionBorradorController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly QuoteDraftServiceInterface $draft) {}
    public function sincronizarBorrador(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'draft_id'  => 'required|string',
                'data'      => 'required|array'
            ]);
            $user = $request->user();
            $this->draft->sync($request->input('draft_id'), $request->input('pto_vta_id'), $request->input('data'), $user);
            return $this->buildSuccessResponse('Proceso realizado correctamente', 200);
        }, 'laravel', 'CotizacionBorradorController.sync');
    }


    public function obtenerBorrador(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'draft_id'  => 'required|string',
            ]);
            $user = $request->user();
            $data = $this->draft->getDraft($request->input('draft_id'), $user);
            return $this->successResponse('Borrador obtenido correctamente', $data, 200);
        }, 'laravel', 'CotizacionBorradorController.obtenerBorrador');
    }


    public function listarBorradores(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'pto_vta_id'  => 'required|string',
            ]);
            $user = $request->user();
            $data = $this->draft->listDrafts($request->input('pto_vta_id'), $user);
            return $this->successResponse('Listado de borradores', $data, 200);
        }, 'laravel', 'CotizacionBorradorController.listarBorradores');
    }

    public function eliminarBorrador(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'draft_id'  => 'required|string',
            ]);
            $user = $request->user();
            $this->draft->deleteDraft($request->input('draft_id'), $user);
            return $this->buildSuccessResponse('Borrador eliminado correctamente', 200);
        }, 'laravel', 'CotizacionBorradorController.eliminarBorrador');
    }
}
