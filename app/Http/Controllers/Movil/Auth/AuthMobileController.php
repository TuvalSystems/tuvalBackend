<?php

namespace App\Http\Controllers\Movil\Auth;

use App\Http\Controllers\Controller;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthMobileController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly UserServiceInterface $userService) {}



    public function login(Request $request) : JsonResponse
    {
        return $this->handleApiAction(function () use ($request) : JsonResponse {
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
            $userName = data_get($validated, 'username');
            $password = data_get($validated, 'password');
            $data = $this->userService->authenticateUserMobile($userName, $password);
            return $this->successResponse('Login Exitoso', $data, 200);
        }, 'laravel', 'login');
    }
}
