<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Services\GestionOperativa\Contracts\ClientBranchServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GestionSucursalesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(protected readonly ClientBranchServiceInterface $clientBranchService) {}


    public function listaSucursales(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->input('filters', []);
            $data = $this->clientBranchService->listClientBranches($filters);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'listaSucursales');
    }


    public function obtenerDetalleSucursal(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $id = $request->input('id', null);
            $data = $this->clientBranchService->getDetailClientBranches($id);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'laravel', 'obtenerDetalleSucursal');
    }
}
