<?php

namespace App\Http\Controllers\Admin\Perfiles;

use App\Http\Controllers\Controller;
use App\Services\Admin\Contracts\ProfileServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function  __construct(
        protected readonly ProfileServiceInterface $profile
    ) {
        $this->middleware('web.section:sistemas-seguridad-perfiles');
    }

    public function lista(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {

            $status = $request->input('status', null);
            $platform = $request->input('platform');
            $search = $request->input('search');
            $data = $this->profile->getProfiles($status, $platform, $search);
            return $this->successResponse('Lista de perfiles obtenida', $data, 200);
        }, 'laravel', 'ProfileController.lista');
    }


    public function create(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate([
                'name'     => ['required', 'string', 'max:100'],
                'platform' => ['required', 'in:web,app'],
                'state'    => ['required', 'in:active,inactive'],
            ]);

            $profile = $this->profile->createProfile($data);
            return $this->successResponse('Perfil creado exitosamente', $profile, 201);
        }, 'laravel', 'ProfileController.create');
    }

    public function update(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate([
                'id'       => ['required', 'integer', 'exists:perfiles,id'],
                'name'     => ['required', 'string', 'max:100'],
                'platform' => ['required', 'in:web,app'],
                'state'    => ['required', 'in:active,inactive'],
            ]);
            $profile = $this->profile->updateProfile($data);
            return $this->successResponse('Perfil actualizado exitosamente', $profile, 200);
        }, 'laravel', 'ProfileController.update');
    }

    public function delete(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate([
                'id' => ['required', 'integer', 'exists:perfiles,id'],
            ]);

            $this->profile->deleteProfile(data_get($data, 'id'));

            return $this->successResponse('Perfil eliminado exitosamente', null, 200);
        }, 'laravel', 'ProfileController.delete');
    }

    public function assignedSections(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate([
                'perfil_id' => ['required', 'integer', 'exists:perfiles,id'],
            ]);
            $assignedSections = $this->profile->getAssignedSections(data_get($data, 'perfil_id'));
            return $this->successResponse('Perfil actualizado exitosamente', $assignedSections, 200);
        }, 'laravel', 'ProfileController.assignedSections');
    }

    public function saveSections(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $request->validate([
                'perfil_id'         => ['required', 'integer', 'exists:perfiles,id'],
                'section_ids'       => ['array'],
                'section_ids.*'     => ['integer', 'exists:sections,id'],
                'home_section_id'   => ['nullable', 'integer', 'exists:sections,id'],
            ]);

            $this->profile->saveSections(
                (int) data_get($data, 'perfil_id'),
                data_get($data, 'section_ids', []),
                data_get($data, 'home_section_id') !== null
                    ? (int) data_get($data, 'home_section_id')
                    : null
            );

            return $this->successResponse('Accesos guardados correctamente', [], 200);
        }, 'laravel', 'ProfileController.saveSections');
    }
}
