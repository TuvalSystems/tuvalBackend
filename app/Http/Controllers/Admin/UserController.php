<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly UserServiceInterface $service) {}



    public function listaUsuarios(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters        = $request->input('filters', []);
            $data = $this->service->listUsers($filters);
            return $this->successResponse('Lista de usuarios Obtenida', $data, 200);
        }, 'laravel', 'lista usuarios');
    }

    public function changeStatusGpUser(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $userId = $request->input('id');
            $this->service->changeStatusGpUser($userId);
            return $this->buildSuccessResponse('Estado actualizado correctamente!', 200);
        }, 'laravel', 'UserController.changeStatusGpUser');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $id = $request->input('id');
            $this->service->resetPassword($id);
            return $this->buildSuccessResponse('Contraseña Reseteada correctamente!', 200);
        }, 'laravel', 'UserController.resetPassword');
    }

    public function obtenerDetalleUsuario(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $id = $request->input('id');

            $data = $this->service->getDetailUser($id);
            return $this->successResponse('Detalle Obtenido', $data, 200);
        }, 'laravel', 'UserController.resetPassword');
    }
    public function obtenerListaPerfilesWeb(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->service->getProfilesWeb();
            return $this->successResponse('Lista de Perfiles Web', $data, 200);
        }, 'laravel', 'UserController. obtenerListaPerfilesWeb');
    }
    public function obtenerListaPerfilesApp(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->service->getProfilesApp();
            return $this->successResponse('Lista de Perfiles App', $data, 200);
        }, 'laravel', 'UserController. obtenerListaPerfilesWeb');
    }
}
