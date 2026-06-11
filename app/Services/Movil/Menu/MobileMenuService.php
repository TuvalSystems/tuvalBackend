<?php

namespace App\Services\Movil\Menu;

use App\Services\Movil\Menu\Contracts\MobileMenuServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileMenuService implements MobileMenuServiceInterface
{
    public function __construct() {}
    public function getMenuLayout(int $perfilAppId): array
    {
        $sections = DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->where('perfil_sections.perfil_id', '=', $perfilAppId)
            ->where('sections.platform', '=', 'app')
            ->where('perfil_sections.state', 1)
            ->where('sections.status', 1)
            ->select([
                'sections.id',
                'sections.parent_id',
                'sections.url',
                'sections.name',
                'sections.icon',
                'sections.sort_order',
                'sections.route_name',
            ])
            ->orderBy('sections.parent_id')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.name')
            ->get()
            ->map(fn($sec) => (array) $sec)
            ->toArray();

        $tree = $this->buildSectionTree($sections);

        return [
            'items' => $this->formatForMobile($tree),
            'home'  => $this->formatHomeForMobile(
                $this->getHomeSection($perfilAppId)
            ),
        ];
    }


    private function getHomeSection(int $perfilAppId): ?object
    {
        return DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->where('perfil_sections.perfil_id', $perfilAppId)
            ->where('perfil_sections.home', 'yes')
            ->where('perfil_sections.state', 1)
            ->where('sections.status', 1)
            ->where('sections.platform', 'app')
            ->select([
                'sections.id',
                'sections.parent_id',
                'sections.url',
                'sections.name',
                'sections.icon',
                'sections.sort_order',
                'sections.route_name',
            ])
            ->orderBy('sections.sort_order')
            ->first();
    }

    private function buildSectionTree(array $elements, $parentId = null): array
    {
        $branch = [];

        foreach ($elements as $element) {
            $idParent   = data_get($element, 'parent_id');
            $elementId  = data_get($element, 'id');

            if ($idParent == $parentId) {
                $children = $this->buildSectionTree($elements, $elementId);

                if (!empty($children)) {
                    $element['children'] = $children;
                }

                $branch[] = $element;
            }
        }

        return $branch;
    }

    private function formatForMobile(array $elements): array
    {
        return collect($elements)->map(function (array $item) {
            $children       = $item['children'] ?? [];
            $hasChildren    = count($children) > 0;

            return [
                'id'        => (int) $item['id'],
                'key'       => $this->buildMenuKey($item),
                'label'     => $item['name'],
                'icon'      => $item['icon'],
                'route'     => $hasChildren ? null : $this->resolveMobileRoute($item),
                'children'  => $hasChildren
                    ? $this->formatForMobile($children)
                    : [],
            ];
        })->values()->toArray();
    }

    private function formatHomeForMobile(?object $home): ?array
    {
        if (!$home) {
            return null;
        }
        $item = (array) $home;
        return [
            'id'    => (int) $item['id'],
            'key'   => $this->buildMenuKey($item),
            'label' => $item['name'],
            'icon'  => $item['icon'],
            'route' => $this->resolveMobileRoute($item),
        ];
    }

    private function buildMenuKey(array $item): string
    {
        if (!empty($item['route_name'])) {
            return Str::slug($item['route_name'], '_');
        }

        if (!empty($item['url'])) {
            return Str::slug($item['url'], '_');
        }

        return Str::slug($item['name'], '_');
    }

    private function resolveMobileRoute(array $item): ?string
    {
        return $item['route_name'] ?? $item['url'] ?? null;
    }
}
