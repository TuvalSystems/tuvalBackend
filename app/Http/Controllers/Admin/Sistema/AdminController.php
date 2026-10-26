<?php

namespace App\Http\Controllers\Admin\Sistema;

use App\Http\Controllers\Controller;
use App\Services\Admin\Logs\Contracts\AdminLogServiceInterface;
use App\Services\Admin\Ubicaciones\Contracts\LocationServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminController extends Controller
{
    private const LOG_CHANNEL = 'stack';
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly AdminLogServiceInterface $admin,
        protected readonly LocationServiceInterface $location
    ) {
        $this->middleware('web.section:sistemas-sync-logs,sistemas-logs')
            ->only(['listaLogs', 'obtenerDetalleLog']);
    }

    public function listaLogs(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $filters = $request->input('filters', []);
            $response = $this->admin->fetchListLogs($filters);
            return $this->successResponse('Listado de logs', $response, 200);
        }, self::LOG_CHANNEL, 'listaLogs');
    }

    public function obtenerDetalleLog(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            $info = $this->admin->getDetail($id);
            return $this->successResponse('Información Obtenida Correctamente.!', $info);
        }, self::LOG_CHANNEL, 'obtenerDetalleLog');
    }


    /** listas de regiones, provincias, cantones, parroquias */

    public function listaRegiones(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $lista = $this->location->getRegions();
            return $this->successResponse('Lista obtenida correctamente', $lista);
        }, self::LOG_CHANNEL, 'listaRegiones');
    }

    public function listaProvincias(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id' => 'required|integer|exists:regions,id'
            ]);
            $id = data_get($validate, 'id');
            $lista = $this->location->getProvinces($id);
            return $this->successResponse('Lista Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaProvincias');
    }

    public function listaCantones(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id' => 'required|integer|exists:provincias,id'
            ]);
            $id = data_get($validate, 'id');
            $lista = $this->location->getCantons($id);
            return $this->successResponse('Lista Obtenida correctamente', $lista);
        }, self::LOG_CHANNEL, 'listaCantones');
    }

    public function listaParroquias(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id' => 'required|integer|exists:cantons,id'
            ]);
            $id = data_get($validate, 'id');
            $lista = $this->location->getParishes($id);
            return $this->successResponse('lista Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaParroquias');
    }
}
