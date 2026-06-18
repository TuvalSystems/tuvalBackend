<?php

namespace App\Services\User\Contracts;

use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{

    public function listUsers(array $filters): LengthAwarePaginator;

    public function updateMobileAppAccess(int $id);
     public function updateAdminCollectionAccess(int $id);

     public function authenticateUserWeb(string $userName, string $password) : array;
     public function authenticateUserMobile(string $userName, string $password): array;
    // cambio de contraseña
    public function changePassword(array $data, GpUsuario $user);
    /**
     * Reset password
     */

    public function resetPassword(int $id);

    /**
     * Cambio de status
     */
    public function changeStatusGpUser(int $userId);


    /**notificaciones */

    public function getAllNotifications(int $userId, int $perPage = 10, $search, $sortBy, $sortOrder);
    public function markAsReadNotify(string $notifyId, int $userId);
    public function markAllReadNotify(int $userId);

    public function getWarehouesByUser(string $userId, string $ptoVtaId);

    public function getAllPaymentConditions();
    public function getAllPaymentMethods(string $condId);

    public function getDetailUser(int $id): ?GpUsuario;
     public function getDetailUserMovil(int $id): array;
    public function getProfilesApp();
    public function getProfilesWeb();
     public function updateUserProfiles(array $payload);

     public function getPointSalesMobile(GpUsuario $user);
}
