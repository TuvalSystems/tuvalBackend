<?php

namespace App\Services\Admin;

use App\Services\Admin\Contracts\MenuServiceInterface;
use Illuminate\Support\Facades\DB;

class MenuService implements MenuServiceInterface
{
    public function __construct()
    {
        //add comment
    }



    public function getFullMenuLayout(int $perfilId): array
    {

        $sections = DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->where('perfil_sections.perfil_id', '=', $perfilId)
            ->where('sections.platform', '=', 'web')
            ->where('perfil_sections.state', 1)
            ->select('sections.id', 'sections.parent_id', 'sections.url', 'sections.name', 'sections.icon', 'sections.sort_order', DB::raw('sections.route_name as `to`'))
            ->orderBy('sections.parent_id')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.name')
            ->get()
            ->map(fn($sec) => (array) $sec)
            ->toArray();

        $tree = $this->buildSectionTree($sections);

        return [
            'arbol' =>  $this->formatForOneUI($tree),
            'home'  =>  $this->getHomeSection($perfilId)
        ];
    }

    private function getHomeSection(int $perfilId): ?object
    {
        return DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->where('perfil_sections.perfil_id', $perfilId)
            ->where('perfil_sections.home', 'yes')
            ->where('perfil_sections.state', 1)
            ->where('sections.status', 1)
            ->whereIn('sections.platform', ['web'])
            ->select([
                'sections.id',
                'sections.parent_id',
                'sections.url',
                'sections.name',
                'sections.icon',
                'sections.sort_order',
                'sections.platform',
                'sections.status',
                DB::raw('sections.route_name as `to`'),
            ])
            ->orderBy('sections.sort_order')
            ->first();
    }


    private function buildSectionTree(array $elements, $parentId = null): array
    {
        $branch = [];
        foreach ($elements as $element) {
            $idParent = data_get($element, 'parent_id');
            $elementId = data_get($element, 'id');

            if ($idParent == $parentId) {
                $children = $this->buildSectionTree($elements, $elementId);

                if (!empty($children)) {
                    $element['sub'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }

    private function formatForOneUI(array $elements): array
    {
        foreach ($elements as $key => $item) {
            $hasChildren = isset($item['sub']) && count($item['sub']) > 0;

            if ($hasChildren) {
                unset($elements[$key]['to']);
                $elements[$key]['sub'] = $this->formatForOneUI($item['sub']);
            }

            if (is_null($item['parent_id'])) {
                $elements[$key]['subActivePaths'] = $item['url'] ?? '';
            }

            unset(
                $elements[$key]['id'],
                $elements[$key]['parent_id'],
                $elements[$key]['url'],
                $elements[$key]['sort_order']
            );
        }

        return $elements;
    }
}
