<?php

namespace App\Services\Movil;

use App\Exceptions\Movil\AppVersionException;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Movil\AppVersion;
use App\Services\Movil\Contracts\AppServiceInterface;
use App\Traits\RegisterAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppService implements AppServiceInterface
{
    use RegisterAudit;

    public function getLatestVersion(string $platform = 'android'): array
    {
        $version = AppVersion::latestForPlatform($platform)->first();
        if (!$version) {
            throw AppVersionException::notFound();
        }
        return [
            'platform'               => $version->platform,
            'latest_version'         => $version->version_name,
            'latest_build'           => $version->build_number,
            'minimum_required_build' => $version->minimum_required_build,
            'apk_url'                => $version->apk_url,
            'message'                => $version->message,
            'release_notes'          => $version->release_notes,
            'checksum_sha256'        => $version->checksum_sha256,
            'apk_size_bytes'         => $version->apk_size_bytes,
        ];
    }


    public function listVersions(array $filters): LengthAwarePaginator
    {
        $search = data_get($filters, 'search', '');
        $perpage = data_get($filters, 'perpage', 10);
        $sortField = data_get($filters, 'sortField', 'created_at');
        $sortOrder = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $allowedSorts = [
            'id'                        => 'ap.id',
            'platform'                  => 'ap.platform',
            'version_name'              => 'ap.version_name',
            'build_number'              => 'ap.build_number',
            'minimum_required_build'    => 'ap.minimum_required_build',
            'apk_size_bytes'            => 'ap.apk_size_bytes',
            'is_active'                 => 'ap.is_active',
            'created_at'                => 'ap.created_at',
        ];

        $orderColumn = data_get($allowedSorts, $sortField, 'up.created_at');

        $query = AppVersion::from('app_versions as ap')
            ->select([
                'ap.id',
                'ap.platform',
                'ap.version_name',
                'ap.build_number',
                'ap.minimum_required_build',
                'ap.apk_size_bytes',
                'ap.is_active',
                'ap.apk_url',
                'ap.created_at'
            ])
            ->orderByDesc('build_number');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('platform', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }
        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }


    public function storeVersion(array $payload, GpUsuario $user, UploadedFile  $file): AppVersion
    {

        if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
            throw AppVersionException::invalidApkFile();
        }

        $platform = data_get($payload, 'platform', 'android');
        $versionName = data_get($payload, 'version_name');
        $buildNumber = (int) data_get($payload, 'build_number');
        $minimumRequiredBuild = (int) data_get($payload, 'minimum_required_build', $buildNumber);
        if (!$versionName) {
            throw AppVersionException::invalidVersionName();
        }

        if ($buildNumber <= 0) {
            throw  AppVersionException::invalidBuildNumber();
        }

        if ($minimumRequiredBuild <= 0) {
            throw  AppVersionException::invalidBuildNumber();
        }

        if ($minimumRequiredBuild > $buildNumber) {
            throw  AppVersionException::invalidMinimumRequiredBuild();
        }

        $exists = AppVersion::where('platform', $platform)
            ->where('build_number', $buildNumber)
            ->exists();

        if ($exists) {
            throw AppVersionException::duplicatedBuildNumber();
        }

        $safeVersion = Str::slug($versionName, '-');
        $fileName = "tuvalapp-v{$safeVersion}-build{$buildNumber}.apk";
        $path = $file->storeAs('apks', $fileName, 'public');

        $fullPath = storage_path('app/public/' . $path);
        $checksum = hash_file('sha256', $fullPath);

        $url = asset('storage/' . $path);

        return   DB::transaction(function () use (
            $payload,
            $user,
            $file,
            $platform,
            $versionName,
            $buildNumber,
            $minimumRequiredBuild,
            $fileName,
            $path,
            $url,
            $checksum
        ) {
            $version = AppVersion::create([
                'platform'               => $platform,
                'version_name'           => $versionName,
                'build_number'           => $buildNumber,
                'minimum_required_build' => $minimumRequiredBuild,
                'apk_file_name'          => $fileName,
                'apk_path'               => $path,
                'apk_url'                => $url,
                'apk_size_bytes'         => $file->getSize(),
                'checksum_sha256'        => $checksum,
                'message'                => data_get($payload, 'message'),
                'release_notes'          => data_get($payload, 'release_notes'),
                'is_active'              => filter_var(data_get($payload, 'is_active', true), FILTER_VALIDATE_BOOLEAN),
                'created_by_user_id'     => $user->id,
            ]);

            $this->createHistoryRecord(
                $user,
                $version,
                'CREATE',
                null,
                $version->toArray()
            );

            return $version;
        });
    }


    public function setActive(int $id, bool $isActive, GpUsuario $user)
    {

        $version = AppVersion::find($id);
        if (!$version) {
            throw AppVersionException::notFound();
        }
        $oldData = $version->toArray();
        $version->update([
            'is_active'             => $isActive,
            'updated_by_user_id'    => $user->id
        ]);
        $version->refresh();
        $this->createHistoryRecord(
            $user,
            $version,
            'UPDATE_STATUS',
            $oldData,
            $version->toArray()
        );

        return $version;
    }


    public function deleteVersion(int $id, GpUsuario $user)
    {
        $version = AppVersion::find($id);
        $oldData = ['old_version' => $version];
        if (!$version) {
            throw AppVersionException::notFound();
        }

        DB::transaction(function () use ($version, $user, $oldData) {
            if ($version->apk_path && Storage::disk('public')->exists($version->apk_path)) {
                Storage::disk('public')->delete($version->apk_path);
            }
            $this->createHistoryRecord($user, $version, 'DELETE', $oldData, []);
            return (bool) $version->delete();
        });
    }
}
