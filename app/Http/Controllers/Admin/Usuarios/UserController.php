<?php

namespace App\Http\Controllers\Admin\Usuarios;

use App\Http\Controllers\Controller;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly UserServiceInterface $service)
    {
        $this->middleware('web.section:sistemas-seguridad-usuarios')
            ->except(['actualizarContrasena', 'obtenerUsuarioApp']);
    }

    public function listaUsuarios(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters        = $request->input('filters', []);
            $data = $this->service->listUsers($filters);
            return $this->successResponse('Lista de usuarios Obtenida', $data, 200);
        }, 'laravel', 'lista usuarios');
    }

    public function actualizarEstadoUsuario(Request $request): JsonResponse
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

    public function actualizarContrasena(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate(
                ['password' => 'required|min:8'],
                [
                    'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                    'password.required' => 'La contraseña es requerida'
                ]
            );
            $data = $request->all();
            $user = $request->user();
            $response =  $this->service->changePassword($data, $user);
            return $this->successResponse('Contraseña actualizada correctamente!', ['must_change_password' => $response->must_change_password], 200);
        }, 'laravel', 'changePassword');
    }

    public function resetearContrasenaUsuario(Request $request): JsonResponse
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

    public function actualizarAccesoApp(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $id = $request->input('id');
            $this->service->updateMobileAppAccess($id);
            return $this->buildSuccessResponse('Acceso Actualizado Correctamente',  200);
        }, 'laravel', 'actualizarAccesoApp');
    }


    public function actualizarAdminCobros(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $id = $request->input('id');
            $this->service->updateAdminCollectionAccess($id);
            return $this->buildSuccessResponse('Acceso Actualizado Correctamente',  200);
        }, 'laravel', 'actualizarAdminCobros');
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


    public function actualizarPerfilesUsuario(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $request->validate([
                'form.user_id' => 'required|integer|exists:gp_usuarios,id',
            ]);
            $payload = $request->input('form');
            $this->service->updateUserProfiles($payload);
            return $this->buildSuccessResponse("Perfil actualizado correctamente", 200);
        }, 'laravel', 'actualizarPerfilesUsuario');
    }

    public function obtenerUsuarioApp()
    {
        return $this->handleApiAction(function (): JsonResponse {
            $user = auth()->user();
            $data = $this->service->getDetailUserMovil($user->id);
            return $this->successResponse('Información de Usuario', $data, 200);
        }, 'laravel', 'UserController.obtenerUsuarioApp');
    }
}
