<?php

namespace App\Services\Admin;

use App\Models\Section;
use App\Services\Admin\Contracts\SectionServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SectionService implements SectionServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }

    private const MAX_DEPTH = 3;
    public function getTree(?string $platform = null, bool $onlyActive = false): array
    {
        $query = Section::query()
            ->select([
                'id',
                'parent_id',
                'route_name',
                'url',
                'name',
                'icon',
                'sort_order',
                'platform',
                'status',
                'created_at',
                'updated_at',
            ]);

        if ($platform) {
            $query->whereIn('platform', [$platform, 'both']);
        }

        if ($onlyActive) {
            $query->where('status', true);
        }

        $sections = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->buildTree($sections);
    }

    public function getParentOptions(?int $excludeSectionId = null, ?string $platform = null): array
    {
        $query = Section::query()
            ->select([
                'id',
                'parent_id',
                'name',
                'platform',
                'status',
                'sort_order',
            ]);

        if ($platform) {
            $query->whereIn('platform', [$platform, 'both']);
        }

        $sections = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $excludedIds = [];

        if ($excludeSectionId) {
            $excludedIds = $this->getDescendantIds($excludeSectionId);
            $excludedIds[] = $excludeSectionId;
        }

        return $sections
            ->filter(function (Section $section) use ($excludedIds, $excludeSectionId) {
                if (in_array($section->id, $excludedIds, true)) {
                    return false;
                }

                /*
                 * No permitir seleccionar como padre una sección que ya está en nivel 3.
                 * Si se seleccionara, el hijo quedaría en nivel 4.
                 */
                $parentDepth = $this->calculateDepthBySection($section);

                if (!$excludeSectionId) {
                    return $parentDepth < self::MAX_DEPTH;
                }

                $sectionToMove = Section::find($excludeSectionId);

                if (!$sectionToMove) {
                    return $parentDepth < self::MAX_DEPTH;
                }

                $subtreeHeight = $this->getSubtreeHeight($sectionToMove);

                return ($parentDepth + $subtreeHeight) <= self::MAX_DEPTH;
            })
            ->map(function (Section $section) {
                return [
                    'id'            => $section->id,
                    'name'          => $section->name,
                    'parent_id'     => $section->parent_id,
                    'level'         => $this->calculateDepthBySection($section),
                    'full_name'     => $this->buildFullName($section),
                    'platform'      => $section->platform,
                    'status'        => (bool) $section->status,
                ];
            })
            ->values()
            ->toArray();
    }

    public function create(array $data): Section
    {
        return DB::transaction(function () use ($data) {
            $data = $this->normalizeData($data);

            $this->validateParentExists($data['parent_id']);
            $this->validatePlatform($data['platform']);
            $this->validateDepthForCreate($data['parent_id']);

            return Section::create($data);
        });
    }

    public function update(Section $section, array $data): Section
    {
        return DB::transaction(function () use ($section, $data) {
            $data = $this->normalizeData($data);

            $this->validateParentExists($data['parent_id']);
            $this->validatePlatform($data['platform']);
            $this->validateNotSameParent($section, $data['parent_id']);
            $this->validateNotDescendantParent($section, $data['parent_id']);
            $this->validateDepthForUpdate($section, $data['parent_id']);

            $section->update($data);

            return $section->fresh();
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $section = Section::find($id);
            if (!$section) {
                throw new \RuntimeException(
                    'La sección que intenta eliminar no existe.'
                );
            }
            if ($section->children()->exists()) {
                throw new \RuntimeException(
                    'No se puede eliminar una sección que tiene secciones hijas.'
                );
            }

            $section->delete();
        });
    }

    private function normalizeData(array $data): array
    {
        return [
            'parent_id'     => $data['parent_id'] ?? null,
            'route_name'    => $data['route_name'] ?? null,
            'url'           => $data['url'] ?? null,
            'name'          => $data['name'],
            'icon'          => $data['icon'] ?? null,
            'sort_order'    => isset($data['sort_order'])
                ? (int) $data['sort_order']
                : 0,
            'platform'      => $data['platform'] ?? 'web',
            'status'        => array_key_exists('status', $data)
                ? (bool) $data['status']
                : true,
        ];
    }

    private function validateParentExists(?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $exists = Section::where('id', $parentId)->exists();

        if (!$exists) {
            throw new  \RuntimeException(
                'La sección padre seleccionada no existe.'
            );
        }
    }

    private function validatePlatform(string $platform): void
    {
        if (!in_array($platform, ['web', 'app', 'both'], true)) {
            throw new  \RuntimeException(
                'La plataforma seleccionada no es válida.'
            );
        }
    }

    private function validateDepthForCreate(?int $parentId): void
    {
        $newLevel = $this->calculateDepthByParentId($parentId) + 1;

        if ($newLevel > self::MAX_DEPTH) {
            throw new  \RuntimeException(
                'No se permite crear más de 3 niveles de secciones.'
            );
        }
    }

    private function validateDepthForUpdate(Section $section, ?int $newParentId): void
    {
        $parentDepth = $this->calculateDepthByParentId($newParentId);
        $subtreeHeight = $this->getSubtreeHeight($section);

        $finalDepth = $parentDepth + $subtreeHeight;

        if ($finalDepth > self::MAX_DEPTH) {
            throw new  \RuntimeException(
                'No se permite mover la sección porque superaría los 3 niveles permitidos.'
            );
        }
    }

    private function validateNotSameParent(Section $section, ?int $parentId): void
    {
        if ($parentId && $section->id === $parentId) {
            throw new  \RuntimeException(
                'Una sección no puede ser padre de sí misma.'
            );
        }
    }

    private function validateNotDescendantParent(Section $section, ?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $descendantIds = $this->getDescendantIds($section->id);

        if (in_array($parentId, $descendantIds, true)) {
            throw new   \RuntimeException(
                'No se puede mover una sección dentro de sus propias secciones hijas.'
            );
        }
    }

    private function calculateDepthByParentId(?int $parentId): int
    {
        if (!$parentId) {
            return 0;
        }

        $depth = 0;
        $parent = Section::find($parentId);

        while ($parent) {
            $depth++;

            if (!$parent->parent_id) {
                break;
            }

            $parent = Section::find($parent->parent_id);
        }

        return $depth;
    }

    private function calculateDepthBySection(Section $section): int
    {
        $depth = 1;
        $parentId = $section->parent_id;

        while ($parentId) {
            $parent = Section::find($parentId);

            if (!$parent) {
                break;
            }

            $depth++;
            $parentId = $parent->parent_id;
        }

        return $depth;
    }

    private function getSubtreeHeight(Section $section): int
    {
        $children = Section::where('parent_id', $section->id)->get();

        if ($children->isEmpty()) {
            return 1;
        }

        $maxChildHeight = 0;

        foreach ($children as $child) {
            $childHeight = $this->getSubtreeHeight($child);

            if ($childHeight > $maxChildHeight) {
                $maxChildHeight = $childHeight;
            }
        }

        return 1 + $maxChildHeight;
    }

    private function getDescendantIds(int $sectionId): array
    {
        $children = Section::where('parent_id', $sectionId)->get();

        $ids = [];

        foreach ($children as $child) {
            $ids[] = $child->id;

            $ids = array_merge(
                $ids,
                $this->getDescendantIds($child->id)
            );
        }

        return $ids;
    }

    private function buildFullName(Section $section): string
    {
        $names = [$section->name];
        $parentId = $section->parent_id;

        while ($parentId) {
            $parent = Section::find($parentId);

            if (!$parent) {
                break;
            }

            array_unshift($names, $parent->name);

            $parentId = $parent->parent_id;
        }

        return implode(' / ', $names);
    }

    private function buildTree(Collection $sections, ?int $parentId = null): array
    {
        return $sections
            ->filter(function (Section $section) use ($parentId) {
                if ($parentId === null) {
                    return $section->parent_id === null;
                }

                return (int) $section->parent_id === $parentId;
            })
            ->map(function (Section $section) use ($sections) {
                return [
                    'id'                => $section->id,
                    'parent_id'         => $section->parent_id,
                    'route_name'        => $section->route_name,
                    'url'               => $section->url,
                    'name'              => $section->name,
                    'icon'              => $section->icon,
                    'sort_order'        => $section->sort_order,
                    'platform'          => $section->platform,
                    'status'            => (bool) $section->status,
                    'children'          => $this->buildTree($sections, $section->id),
                ];
            })
            ->values()
            ->toArray();
    }
}
