<?php

namespace App\Services\Admin\Perfiles;

use App\Models\Admin\Perfiles\Perfiles;
use App\Services\Admin\Perfiles\Contracts\ProfileServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ProfileService implements ProfileServiceInterface
{
    public function __construct() {}

    public function getProfiles(?string $status = null, ?string $platform = null, ?string $search = null): Collection
    {
        $query = Perfiles::query()
            ->platform($platform)
            ->state($status)
            ->search($search)
            ->select([
                'id',
                'name',
                'platform',
                'state'
            ]);

        return  $query
            ->orderBy('platform')
            ->orderBy('name')->get();
    }


    public function createProfile(array $data): Perfiles
    {
        return Perfiles::create([
            'name'     => data_get($data, 'name'),
            'platform' => data_get($data, 'platform'),
            'state'    => data_get($data, 'state', 'active'),
        ]);
    }

    public function updateProfile(array $data): Perfiles
    {
        $profile = Perfiles::findOrFail(data_get($data, 'id'));

        $profile->update([
            'name'     => data_get($data, 'name'),
            'platform' => data_get($data, 'platform'),
            'state'    => data_get($data, 'state'),
        ]);

        return $profile->fresh();
    }


    public function deleteProfile(int $id): void
    {
        $profile = Perfiles::findOrFail($id);
        if ($profile->usersApp()->exists() || $profile->users()->exists()) {
            throw new \RuntimeException('No se puede eliminar el perfil porque tiene usuarios asociados');
        }

        if ($profile->sections()->exists()) {
            throw new \RuntimeException('No se puede eliminar el perfil porque tiene secciones asociadas');
        }
        $profile->delete();
    }


    public function getAssignedSections(int $perfilId): array
    {
        $profile = Perfiles::findOrFail($perfilId);
        $assignedSections = $profile->sections()
            ->wherePivot('state', 1)
            ->get();

        $homeSection = $assignedSections->first(function ($section) {
            return data_get($section, 'pivot.home') === 'yes';
        });

        return [
            'section_ids' =>  $assignedSections
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->values(),
            'home_section_id' => $homeSection ? (int) $homeSection->id : null,
        ];
    }


    public function saveSections(
        int $perfilId,
        array $sectionIds,
        ?int $homeSectionId = null
    ): void {
        $profile = Perfiles::findOrFail($perfilId);

        $sectionIds = collect($sectionIds)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($homeSectionId !== null) {
            $homeSectionId = (int) $homeSectionId;

            if (!$sectionIds->contains($homeSectionId)) {
                throw ValidationException::withMessages([
                    'home_section_id' => [
                        'La sección home debe estar dentro de las secciones asignadas al perfil.',
                    ],
                ]);
            }
        }

        $syncData = $sectionIds
            ->mapWithKeys(function ($sectionId) use ($homeSectionId) {
                return [
                    $sectionId => [
                        'state'      => 1,
                        'home'       => $homeSectionId === $sectionId ? 'yes' : 'no',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];
            })
            ->toArray();

        $profile->sections()->sync($syncData);
    }
}
