<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class LimpiarLog extends Command
{
    protected $signature = 'limpiar:log
        {--sessions : Elimina todos los archivos de sesión}
        {--cache : Elimina archivos de caché}
        {--views : Elimina vistas compiladas}';

    protected $description = 'Vacia el archivo laravel.log y opcionalmente limpia sesiones, caché y vistas compiladas, restaurando permisos';

    public function handle(): int
    {
        $logFiles = [
            'laravel.log',
            'laravel-queue.log',
            'cron-process.log',
            'soap_errors.log',
            'apiservice.log',
            'devolucion.log',
            'sql_errors.log',
            'reservas_log.log',
            'redis_log.log',
        ];

        // Obtener propietario real del directorio storage
        $owner = $this->getStorageOwner();
        if (!$owner)
            return 1;

        foreach ($logFiles as $fileName) {

            $path = storage_path("logs/$fileName");

            if (!file_exists($path)) {
                file_put_contents($path, '');
                $this->info("📄 El archivo $fileName fue creado porque no existía.");

            }

            // Vaciar archivo
            file_put_contents($path, '');

            $this->setPermissions($path, $owner);

            $this->info("🧹 $fileName vaciado y permisos restaurados (propietario: $owner).");
        }



        // Opcional: sesiones
        if ($this->option('sessions')) {
            $this->deleteFilesIn(storage_path('framework/sessions'), 'archivos de sesión');
        }

        // Opcional: cache
        if ($this->option('cache')) {
            $this->deleteFilesIn(storage_path('framework/cache/data'), 'archivos de caché');
        }

        // Opcional: vistas compiladas
        if ($this->option('views')) {
            $this->deleteFilesIn(storage_path('framework/views'), 'vistas compiladas');
        }

        return 0;
    }

    protected function getStorageOwner(): ?string
    {
        $process = Process::fromShellCommandline("stat -c '%U:%G' " . storage_path());
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn("⚠️ No se pudo obtener el propietario del directorio storage.");
            $this->warn($process->getErrorOutput());
            return null;
        }

        return trim($process->getOutput());
    }

    protected function setPermissions(string $file, string $owner): void
    {
        $commands = [
            ['chown', $owner, $file],
            ['chmod', '664', $file],
        ];

        foreach ($commands as $cmd) {
            $process = new Process($cmd);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->warn("⚠️ Falló: " . implode(' ', $cmd));
                $this->warn($process->getErrorOutput());
            }
        }
    }

    protected function deleteFilesIn(string $path, string $label): void
    {
        if (!is_dir($path)) {
            $this->warn("⚠️ El directorio $label no existe: $path");
            return;
        }

        $deleted = 0;
        foreach (glob("$path/*") as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted++;
            }
        }

        $this->info("🗑️  Se eliminaron $deleted $label.");
    }
}
