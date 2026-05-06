<?php

namespace App\Http\Controllers\Movil;

use App\Http\Controllers\Controller;
use App\Services\Movil\Contracts\AppServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class AppController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(protected readonly AppServiceInterface $appService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $platform = $request->input('platform', null);
            $perpage        = $request->integer('perpage', 10);
            $data           = $this->appService->listVersions($perpage, $platform);
            return $this->successResponse("Lista Obtenida Correctamente", $data, 200);
        }, 'laravel', 'movil - index');
    }


    public function latest(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $platform   = $request->query('platform', 'android');
            $data       = $this->appService->getLatestVersion($platform);
            return $this->successResponse('Versión Obtenida Correctamente', $data, 200);
        }, 'laravel', 'movil - latest');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $validated = $request->validate([
                'platform'                  => ['nullable', 'string', 'max:20'],
                'version_name'              => ['required', 'string', 'max:20'],
                'build_number'              => ['required', 'integer', 'min:1'],
                'minimum_required_build'    => ['required', 'integer', 'min:1'],
                'message'                   => ['nullable', 'string'],
                'release_notes'             => ['nullable', 'string'],
                'is_active'                 => ['nullable', 'boolean'],
                'apk'                       => ['required', 'file', 'max:204800'],
            ]);
            $user = $request->user();
            $file = $request->file('apk');
            $version = $this->appService->storeVersion($validated, $user, $file);
            return $this->successResponse('Versión Creada Correctamente', $version, 200);
        }, 'laravel', 'movil - store');
    }


    public function active(Request $request, int $id)
    {
        return $this->handleApiAction(function () use ($request, $id): JsonResponse {
            $request->validate([
                'is_active' => ['required', 'boolean'],
            ]);
            $user       = $request->user();
            $data       = $this->appService->setActive($id,  $request->boolean('is_active'), $user);
            return $this->successResponse('Versión Actualizada Correctamente', $data, 200);
        }, 'laravel', 'active');
    }


    public function destroy(Request $request, int $id)
    {
        return $this->handleApiAction(function () use ($request, $id) {
            $user       = $request->user();
            $this->appService->deleteVersion($id, $user);
            return $this->buildSuccessResponse('Versión Eliminada Correctamente', 200);
        }, 'laravel', 'movil-destroy');
    }
}
