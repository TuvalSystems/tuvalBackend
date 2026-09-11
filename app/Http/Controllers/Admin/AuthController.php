<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\CleansPayloadData;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;


class AuthController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions, CleansPayloadData;

    public function __construct(protected readonly UserServiceInterface $userService) {}

    public function login(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'username' => ['required', 'string', 'max:100'],
                'password' => ['required', 'string', 'max:100'],
            ], [
                'username.required' => 'El usuario es obligatorio.',
                'username.string'   => 'El usuario debe ser texto.',
                'username.max'      => 'El usuario no debe superar los 50 caracteres.',

                'password.required' => 'La contraseña es obligatoria.',
                'password.max'      => 'La contraseña no debe superar los 100 caracteres.',
            ]);
            $userName = $this->clean($validated, 'username');
            $password = data_get($validated, 'password');
            abort_unless($request->hasSession(), 419, 'Inicialice la sesión CSRF desde un origen autorizado.');
            $data = $this->userService->authenticateUserWeb($userName, $password);
            $request->session()->regenerate();
            return $this->successResponse('Login Exitoso', $data, 200);
        }, 'laravel', 'login');
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

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response(['success' => true]);
    }








    public function session(Request $request): JsonResponse
    {
        return $this->successResponse('Sesión activa',
            $this->userService->getWebSessionContext($request->user()));
    }

    public function migrateSession(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 419);

        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless(
            $request->bearerToken()
            && $token instanceof PersonalAccessToken
            && $token->can('web'),
            403
        );

        $data = $this->userService->getWebSessionContext($user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $token->delete();

        return $this->successResponse('Sesión migrada', $data);
    }

    public function obtenerBodegasPorPuntoDeVentaUsuario(Request $request)
    {
        try {
            $gpUser = $request->user();
            $ptoVta = data_get($request->all(), 'ptoVta', null);
            $warehouses = $this->userService->getWarehouesByUser($gpUser->USERID, $ptoVta);
            return $this->successResponse('Información Obtenida Correctamente', $warehouses, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function obtenerCondicionesDePago()
    {
        try {
            $conditions = $this->userService->getAllPaymentConditions();
            return $this->successResponse('Condiciones de pago obtenidas correctamente', $conditions, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerMetodosDePago(Request $request)
    {
        try {
            $request->validate([
                'condId' => 'required|string',
            ]);
            $methods = $this->userService->getAllPaymentMethods($request->condId);
            return $this->successResponse('Métodos de pago obtenidos correctamente', $methods, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
}
