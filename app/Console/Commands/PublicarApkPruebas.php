<?php

namespace App\Console\Commands;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Movil\AppVersion;
use App\Services\Movil\ArchivoApk;
use App\Services\Movil\Contracts\AppServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class PublicarApkPruebas extends Command
{
    protected $signature = 'app:publicar-apk-pruebas {apk} {manifest}';

    protected $description = 'Publica la APK QA verificada por CI en el servidor de pruebas.';

    public function handle(AppServiceInterface $service): int
    {
        $path = null;
        $checksum = null;
        try {
            if (gethostname() !== 'srv-web02' || DB::connection()->getDriverName() !== 'mysql'
                || DB::connection()->getDatabaseName() !== 'tuvalapi') {
                throw new RuntimeException('Este comando solo permite MySQL tuvalapi en srv-web02.');
            }

            $apk = realpath($this->argument('apk'));
            $manifest = realpath($this->argument('manifest'));
            if (!$apk || !$manifest || !is_file($apk) || !is_readable($apk)) {
                throw new RuntimeException('No se encuentra la APK o su manifiesto.');
            }
            $data = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            Validator::make($data, [
                'package' => ['required', 'in:com.tuvalsa.tuvalapp.qa'],
                'versionName' => ['required', 'string', 'max:50', 'ends_with:-qa'],
                'buildNumber' => ['required', 'integer', 'min:1', 'max:2100000000'],
                'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
                'commit' => ['required', 'regex:/^[a-f0-9]{40}$/'],
                'runUrl' => ['required', 'regex:~^https://github\.com/TuvalSystems/appmovil/actions/runs/[0-9]+$~'],
                'releaseNotes' => ['sometimes', 'required', 'string', 'max:20000'],
            ])->validate();
            $checksum = hash_file('sha256', $apk);
            if (!hash_equals($data['sha256'], $checksum)) {
                throw new RuntimeException('El checksum de la APK no coincide.');
            }
            $user = GpUsuario::where('USERID', 'APP_QA_CI')->firstOrFail();
            $existing = AppVersion::platform('android')->where('build_number', $data['buildNumber'])->first();
            if ($existing) {
                if (!$existing->is_active || $existing->version_name !== $data['versionName']
                    || !hash_equals($existing->checksum_sha256, $checksum)
                    || !Storage::disk('public')->exists($existing->apk_path)
                    || !hash_equals($checksum, hash_file('sha256', Storage::disk('public')->path($existing->apk_path)))) {
                    throw new RuntimeException('El build ya existe con contenido diferente o está inactivo.');
                }
                $this->line(json_encode(['id' => $existing->id, 'build' => $existing->build_number, 'reused' => true]));
                return self::SUCCESS;
            }
            if ((int) $data['buildNumber'] <= (int) AppVersion::platform('android')->max('build_number')) {
                throw new RuntimeException('El build debe ser mayor a los ya registrados.');
            }
            $latest = AppVersion::latestForPlatform('android')->first();
            $minimum = $latest?->minimum_required_build ?? 1;
            $candidate = ArchivoApk::ruta($data['versionName'], (int) $data['buildNumber']);
            if (Storage::disk('public')->exists($candidate)) {
                throw new RuntimeException('Ya existe un archivo con el nombre de destino.');
            }
            $path = $candidate;
            $version = $service->storeVersion([
                'platform' => 'android',
                'version_name' => $data['versionName'],
                'build_number' => (int) $data['buildNumber'],
                'minimum_required_build' => $minimum,
                'message' => 'Nueva versión de pruebas disponible.',
                'release_notes' => $data['releaseNotes'] ?? 'Actualización de la aplicación de pruebas.',
                'is_active' => true,
            ], $user, new UploadedFile($apk, 'app-qa-release.apk', 'application/vnd.android.package-archive', null, true));
            $path = null;
            $this->line(json_encode(['id' => $version->id, 'build' => $version->build_number, 'url' => $version->apk_url, 'reused' => false]));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // El servicio registra en transacción; retirar solo nuestro archivo si el registro falló.
            if ($path && !AppVersion::where('apk_path', $path)->exists()
                && Storage::disk('public')->exists($path)
                && hash_equals($checksum, hash_file('sha256', Storage::disk('public')->path($path)))) {
                Storage::disk('public')->delete($path);
            }
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
