<?php

namespace App\Services\User\Contracts;

use App\Models\GpUsuario;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{

    public function listUsers(array $filters): LengthAwarePaginator;

    public function validateStatusGpUser($credentials): bool;
    public function validateCredentials(Request $request): array;
    public function validateUserCredentials(array $credentials): array;
    public function isValidUser(array $validationResponse): bool;
    public function isValidUserWeb(array $credentials): bool;
    public function getActiveUser($username): ?Model;

    public function buildSuccessResponseUser($user): array;

    // cambio de contraseña
    public function changePassword($data, GpUsuario $user);

    /**
     * Reset password
     */

    public function resetPassword($data);

    /**
     * Cambio de status
     */
    public function changeStatusGpUser(string $userId);

    /**notificaciones */

    public function getAllNotifications(int $userId, int $perPage = 10, $search, $sortBy, $sortOrder);
    public function markAsReadNotify(string $notifyId, int $userId);
    public function markAllReadNotify(int $userId);

    public function getWarehouesByUser(string $userId, string $ptoVtaId);

    public function getAllPaymentConditions();
    public function getAllPaymentMethods(string $condId);
}
