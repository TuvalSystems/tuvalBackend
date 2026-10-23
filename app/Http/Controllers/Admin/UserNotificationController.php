<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\User\Contracts\UserServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    use JsonResponseTrait;

    public function __construct(protected readonly UserServiceInterface $userService) {}

    public function index(Request $request)
    {
        $user = $request->user()?->id ?? $request->input('userId');
        $perPage = max(1, min(100, (int) $request->input('perPage', 10)));
        $search = $request->input('search', null);
        $sortOrder = $request->input('orderBy', null);
        $sortBy = $request->input('sortBy', null);

        try {
            $data = $this->userService->getAllNotifications($user, $perPage, $search, $sortBy, $sortOrder);
            return $this->successResponse('Notificaciones', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function marcarNotificacion(Request $request)
    {
        $notifyId = $request->input('notifyId');
        $user = $request->user()?->id ?? $request->input('userId');
        try {
            $data = $this->userService->markAsReadNotify($notifyId, $user);
            return $this->successResponse('Notificacion marcada con exito', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function marcarTodasNotificaciones(Request $request)
    {
        $user = $request->user()?->id ?? $request->input('userId');
        try {
            $this->userService->markAllReadNotify($user);
            return $this->successResponse('Todas las notificaciones marcadas con exito', null, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
}
