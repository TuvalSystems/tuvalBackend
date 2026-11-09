<?php

namespace App\Http\Controllers\Ventas\Cotizaciones\Borradores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Cotizaciones\StoreQuotationItemsDraftRequest;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuoteDraftServiceInterface;
use App\Services\Ventas\Cotizaciones\Borradores\Contracts\QuotationItemsDraftServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotizacionBorradorController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    private const TEST_QUOTATION_USER_ID = 6;

    public function __construct(
        protected readonly QuoteDraftServiceInterface $draft,
        protected readonly QuotationItemsDraftServiceInterface $itemsDraft
    ) {}

    public function crearBorradorDesdeArticulos(StoreQuotationItemsDraftRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $user = GpUsuario::find(self::TEST_QUOTATION_USER_ID);
            $result = $this->itemsDraft->create($request->validated(), $user);
            return $this->successResponse("Cotización borrador {$result['numero']} creada.", $result, 201);
        }, 'cotizaciones', 'crear borrador por items');
    }

    public function sincronizarBorrador(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'draft_id'  => 'required|string',
                'data'      => 'required|array'
            ]);
            $user = $request->user();
            $this->draft->sync($request->input('draft_id'),$request->input('pv_id'), $request->input('pto_vta_id'), $request->input('data'), $user);
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
                'pv_id'  => 'required|integer',
            ]);
            $user = $request->user();
            $data = $this->draft->listDrafts($request->input('pv_id'), $user);
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
