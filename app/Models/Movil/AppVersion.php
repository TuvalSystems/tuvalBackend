<?php

namespace App\Models\Movil;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $table = 'app_versions';

    protected $fillable = [
        'platform',
        'version_name',
        'build_number',
        'minimum_required_build',
        'apk_file_name',
        'apk_path',
        'apk_url',
        'apk_size_bytes',
        'checksum_sha256',
        'message',
        'release_notes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'build_number'              => 'integer',
        'minimum_required_build'    => 'integer',
        'apk_size_bytes'            => 'integer',
        'is_active'                 => 'boolean',
        'created_by_user_id'        => 'integer',
        'updated_by_user_id'        => 'integer',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeLatestForPlatform($query, string $platform = 'android')
    {
        return $query
            ->where('platform', $platform)
            ->where('is_active', true)
            ->orderByDesc('build_number');
    }


    public function hasUpdateFor(int $currentBuild): bool
    {
        return $currentBuild < $this->build_number;
    }

    public function isRequiredFor(int $currentBuild): bool
    {
        return $currentBuild < $this->minimum_required_build;
    }
}
