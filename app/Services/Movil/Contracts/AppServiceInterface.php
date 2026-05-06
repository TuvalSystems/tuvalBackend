<?php

namespace App\Services\Movil\Contracts;

use App\Models\GpUsuario;
use App\Models\Movil\AppVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

interface AppServiceInterface
{
    public function getLatestVersion(string $platform = 'android'): array;
    public function listVersions($page = 10, ?string $platform = null): LengthAwarePaginator;
    public function storeVersion(array $payload, GpUsuario $user, UploadedFile  $file): AppVersion;
    public function setActive(int $id, bool $isActive, GpUsuario $user);
    public function deleteVersion(int $id, GpUsuario $user);
}
