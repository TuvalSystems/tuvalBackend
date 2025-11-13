<?php

namespace App\Services\User\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Model;

interface UserServiceInterface
{

    public function validateStatusGpUser( $credentials): bool;
    public function validateCredentials(Request $request): array;
    public function validateUserCredentials(array $credentials): array;
    public function isValidUser(array $validationResponse): bool;
    public function isValidUserWeb(array $credentials): bool;
    public function getActiveUser($username): ?Model;

    public function buildSuccessResponseUser($user): JsonResponse;

    // cambio de contraseña
    public function changePassword(Request $request);

    /**
     * Reset password
     */

     public function resetPassword($data);

     /**
      * Cambio de status
      */
     public function changeStatusGpUser($userId);

     /**notificaciones */

      public function getAllNotifications(int $userId, int $perPage = 10, $search, $sortBy, $sortOrder);
      public function markAsReadNotify(string $notifyId, int $userId);
      public function markAllReadNotify(int $userId);
}
