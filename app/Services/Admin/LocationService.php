<?php

namespace App\Services\Admin;

use App\Models\Admin\Canton;
use App\Models\Admin\Parroquia;
use App\Models\Admin\Provincia;
use App\Models\Admin\Region;
use App\Services\Admin\Contracts\LocationServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class LocationService implements LocationServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }
    public function getRegions(): Collection
    {
        return Region::select(['id', 'nombre'])->get();
    }

    public function getProvinces(int $regionId) : Collection
    {
        return Provincia::select(['id', 'nombre'])->where('region_id', $regionId)->get();
    }

    public function getCantons(int $provinceId): Collection
    {
        return Canton::select(['id', 'nombre'])->where('provincia_id', $provinceId)->get();
    }

    public function getParishes(int $cantonId): Collection
    {
        return Parroquia::select(['id', 'nombre'])->where('canton_id', $cantonId)->get();
    }
}
