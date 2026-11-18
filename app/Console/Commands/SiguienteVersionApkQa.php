<?php

namespace App\Console\Commands;

use App\Models\Movil\AppVersion;
use App\Services\Movil\VersionApkQa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SiguienteVersionApkQa extends Command
{
    protected $signature = 'app:siguiente-version-qa';

    protected $description = 'Calcula la siguiente versión QA, independiente de pubspec y producción.';

    public function handle(VersionApkQa $versiones): int
    {
        if (gethostname() !== 'srv-web02' || DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'tuvalapi') {
            $this->error('Este comando solo permite MySQL tuvalapi en srv-web02.');
            return self::FAILURE;
        }

        // Incluir inactivas para no reutilizar números de versiones ya publicadas.
        $this->line($versiones->siguiente(AppVersion::platform('android')->pluck('version_name')));

        return self::SUCCESS;
    }
}
