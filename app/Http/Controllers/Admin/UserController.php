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
                'USERID' => 'required|string',
            ]);
            $userId = $request->input('USERID');
            $this->service->changeStatusGpUser($userId);
            return $this->buildSuccessResponse('Estado actualizado correctamente!', 200);
        }, 'laravel', 'AuthController.changeStatusGpUser');
    }
}
