<?php

namespace App\Services\Admin;

use App\Models\Perfiles;
use App\Services\Admin\Contracts\ProfileServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class ProfileService implements ProfileServiceInterface
{
    public function __construct() {}

    public function getProfiles(?string $platform = null, bool $status, ?string $search = null): Collection
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
}
