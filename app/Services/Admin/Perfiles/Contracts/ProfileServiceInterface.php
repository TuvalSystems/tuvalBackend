<?php

namespace App\Services\Admin\Perfiles\Contracts;

use App\Models\Admin\Perfiles\Perfiles;
use Illuminate\Database\Eloquent\Collection;

interface ProfileServiceInterface
{
    // Define los métodos

    public function getProfiles(?string $status = null, ?string $platform = null, ?string $search = null): Collection;
    public function updateProfile(array $data): Perfiles;
    public function createProfile(array $data): Perfiles;
    public function deleteProfile(int $id): void;
    public function getAssignedSections(int $perfilId): array;
   public function saveSections( int $perfilId,
    array $sectionIds,
    ?int $homeSectionId = null): void;
}
