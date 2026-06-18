<?php

namespace App\Http\Controllers\Movil\Menu;

use App\Http\Controllers\Controller;
use App\Services\Movil\Menu\Contracts\MobileMenuServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMenuController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function  __construct(protected readonly MobileMenuServiceInterface $menu) {}


    public function index(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $user = $request->user();
            $perfilAppId = $user->perfil_app_id;
            return $this->successResponse('Menú obtenido exitosamente', $this->menu->getMenuLayout($perfilAppId), 200);
        }, 'laravel', 'MobileMenuController@index');
    }
}
