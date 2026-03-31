<?php

namespace App\Http\Controllers;

use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;


class AuthController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
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



    public function changePassword(Request $request)
    {
        $request->validate(
            ['password' => 'required|min:8'],
            [
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'password.required' => 'La contraseña es requerida'
            ]
        );
        try {
            $data = $request->all();
            $user = $request->user();
            $response =  $this->userService->changePassword($data, $user);
            return $this->successResponse('Contraseña actualizada correctamente!', ['must_change_password' => $response->must_change_password], 200);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validación fallida', 422);
        } catch (\Exception $th) {
            return $this->errorResponse('Error', $th->getMessage(), 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $this->userService->resetPassword($request->input('USERID'));
            return $this->buildSuccessResponse('Contraseña restaurada correctamente!', 200);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error', $th->getMessage(), 500);
        }
    }


    public function changeStatusGpUser(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $request->validate([
                'USERID' => 'required|string',
            ]);
            $userId = $request->input('USERID');
            $this->userService->changeStatusGpUser($userId);
            return $this->buildSuccessResponse('Estado actualizado correctamente!', 200);
        }, 'laravel', 'AuthController.changeStatusGpUser');
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
