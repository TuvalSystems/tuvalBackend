<?php

namespace App\Http\Controllers;

use App\Models\Notificaciones;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use JsonResponseTrait;
    protected $userService;

    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    public function login(Request $request)
    {
        try {
            $credentials = $this->userService->validateCredentials($request);
            $validationResponse = $this->userService->validateuserCredentials($credentials);

            if (!$this->userService->isValidUser($validationResponse)) {
                return $this->buildErrorResponse($validationResponse['msj'] ?? 'Usuario no encontrado', 404);
            }
            if (!$this->userService->isValidUserWeb($credentials)) {
                return $this->buildErrorResponse('Las credenciales no son validas', 401);
            }
            if (!$this->userService->validateStatusGpUser($credentials)) {
                return $this->buildErrorResponse('El usuario ingresado se encuentra inactivo', 404);
            }
            $user = $this->userService->getActiveUser($credentials['username']);
            if (!$user) {
                return $this->buildErrorResponse('El perfil asignado se encuentra inactivo', 404);
            }
            return $this->userService->buildSuccessResponseUser($user);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }

    }


    /**
     * Función de salida del sistema
     */

    public function logout(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        if ($token = $user->currentAccessToken()) {
            $token->delete();
        }

        return response(['success' => true]);
    }

    public function notificaciones(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();

        $notificacion = Notificaciones::where('user_id', $user->id)
            ->where('estado', 'nuevo')->first();

        if ($notificacion) {

            $notificacion->estado = 'enviado';
            $notificacion->save();

            return response([
                'notificacion' => true,
                'titulo' => $notificacion->titulo,
                'mensaje' => $notificacion->mensaje,

            ]);
        }

        return response([
            'notificacion' => false,
        ]);
    }



    public function changePassword(Request $request)
    {
        $request->validate(
            [
                'password' => 'required|min:8',
            ],
            [
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'password.required' => 'La contraseña es requerida',
            ]
        );

        $data = $request->all();
        return $this->userService->changePassword($data);

    }

    public function resetPassword(Request $request)
    {
        $data = $request->all();
        return $this->userService->resetPassword($data);
    }


    public function changeStatusGpUser(Request $request)
    {
        $userId = $request->all();
        return $this->userService->changeStatusGpUser($userId);
    }
}
