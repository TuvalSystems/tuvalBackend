<?php
namespace App\Services\Admin\Ubicaciones\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface LocationServiceInterface
{
    // Define los métodos
    public function getRegions(): Collection;
    public function getProvinces(int $regionId) : Collection;
    public function getCantons(int $provinceId): Collection;
    public function getParishes(int $cantonId): Collection;


}
