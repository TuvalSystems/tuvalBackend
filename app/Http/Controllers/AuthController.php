<?php

namespace App\Http\Controllers;

use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly UserServiceInterface $userService)
    {
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
            $data = $this->userService->buildSuccessResponseUser($user);
            return $this->successResponse("Login successful", $data, 200);
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
