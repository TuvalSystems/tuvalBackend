<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use App\Services\Admin\Contracts\MenuServiceInterface;
use App\Services\Admin\Contracts\SectionServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class SectionsController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function  __construct(
        protected readonly MenuServiceInterface $menu,
        protected readonly SectionServiceInterface $sections
    ) {}


    //nuevo proceso de secciones
    public function index(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $platform = $request->query('platform');
            $onlyActive = $request->boolean('only_active', false);
            $data = $this->sections->getTree($platform, $onlyActive);
            return $this->successResponse('Lista Obtenida', $data, 200);
        }, 'laravel', 'listarSecciones');
    }

    public function options(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $platform           = $request->query('platform');
            $excludeSectionId   = $request->query('exclude_id');
            $options = $this->sections->getParentOptions($excludeSectionId, $platform);
            return $this->successResponse('Información Obtenida', $options, 200);
        }, 'laravel', 'options');
    }


    public function store(StoreSectionRequest  $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $this->sections->create($request->validated());
            return $this->successResponse('Sección creada con exito!', $data, 200);
        }, 'laravel', 'guardarSeccion');
    }


    public function update(UpdateSectionRequest  $request, Section $section): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $section) {
            $section = $this->sections->update(
                $section,
                $request->validated()
            );
            return $this->successResponse('Sección actualizada con éxito!', $section, 200);
        }, 'laravel', 'guardarSeccion');
    }


    public function eliminarSeccion(Request  $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $id = $request->input('id');
            $this->sections->delete($id);
            return $this->buildSuccessResponse('Sección Eliminada', 200);
        }, 'laravel', 'eliminarSeccion');
    }


    public function obtenerMenu()
    {
        return $this->menu->getFullMenuLayout(1);
    }
}
