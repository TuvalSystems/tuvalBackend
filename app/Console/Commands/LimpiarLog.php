<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LimpiarLog extends Command
{
   protected $signature = 'limpiar:log
        {--sessions : Elimina todos los archivos de sesión (recursivo)}
        {--cache : Elimina archivos de caché (recursivo)}
        {--views : Elimina vistas compiladas (recursivo)}';

    protected $description = 'Vacia laravel.log y otros logs; opcionalmente limpia sesiones, caché y vistas. Ajusta permisos (chmod/chgrp) sin requerir sudo.';

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
            'despachos.log',
            'guia_ventas.log',
            'invoice.log',
            'cobranzas.log'
        ];

        // Detectamos grupo del directorio storage para alinear los archivos
        [$storageUser, $storageGroup] = $this->getStorageOwnerAndGroup();

        foreach ($logFiles as $fileName) {
            $path = storage_path("logs/{$fileName}");

            // Crear si no existe
            if (!is_file($path)) {
                // crear con permisos razonables respetando umask
                touch($path);
                $this->info("📄 Creado: {$fileName}");
            }

            // Truncar de forma segura
            $ok = $this->truncateFile($path);
            if (!$ok) {
                $this->warn("⚠️  No se pudo truncar: {$fileName}");
                continue;
            }

            // Ajustar permisos (rw-rw-r--)
            $this->applyPermissions($path, $storageGroup);

            $ownerShown = $storageUser && $storageGroup ? "{$storageUser}:{$storageGroup}" : 'desconocido';
            // $this->info("🧹 {$fileName} vaciado y permisos ajustados (grupo: {$storageGroup ?? 'n/a'} | ref storage: {$ownerShown}).");
        }

        // Opcionales
        if ($this->option('sessions')) {
            $this->deleteFilesRecursively(storage_path('framework/sessions'), 'archivos de sesión');
        }
        if ($this->option('cache')) {
            // en Laravel, los archivos están bajo framework/cache/data (subdirs)
            $this->deleteFilesRecursively(storage_path('framework/cache/data'), 'archivos de caché');
        }
        if ($this->option('views')) {
            $this->deleteFilesRecursively(storage_path('framework/views'), 'vistas compiladas');
        }

        return self::SUCCESS;
    }

    /**
     * Obtiene usuario y grupo del directorio storage usando funciones POSIX si existen.
     */
    protected function getStorageOwnerAndGroup(): array
    {
        $dir = storage_path();

        $uid = @fileowner($dir);
        $gid = @filegroup($dir);

        $user = function_exists('posix_getpwuid') && $uid !== false
            ? (posix_getpwuid($uid)['name'] ?? null)
            : null;

        $group = function_exists('posix_getgrgid') && $gid !== false
            ? (posix_getgrgid($gid)['name'] ?? null)
            : null;

        return [$user, $group];
    }

    /**
     * Trunca el archivo a tamaño 0 de forma segura.
     */
    protected function truncateFile(string $path): bool
    {
        $h = @fopen($path, 'c+'); // c+ crea si no existe, no trunca; abrimos y truncamos
        if (!$h) {
            return false;
        }
        $ok = @ftruncate($h, 0);
        @fclose($h);
        return $ok;
    }

    /**
     * Aplica permisos al archivo:
     * - chmod 0664
     * - chgrp al grupo del storage si está disponible (cambiar owner requiere root, no se intenta)
     */
    protected function applyPermissions(string $file, ?string $desiredGroup): void
    {
        // chmod: rw-rw-r--
        if (!@chmod($file, 0664)) {
            $this->warn("⚠️  chmod 0664 falló: {$file}");
        }

        // chgrp solo si sabemos el grupo y el proceso tiene permiso para cambiarlo
        if ($desiredGroup) {
            if (!@chgrp($file, $desiredGroup)) {
                // No es crítico (depende de membresía de grupos)
                $this->warn("⚠️  chgrp {$desiredGroup} falló: {$file}");
            }
        }
    }

    /**
     * Elimina **archivos** recursivamente dentro de $path (deja los directorios).
     */
    protected function deleteFilesRecursively(string $path, string $label): void
    {
        if (!is_dir($path)) {
            $this->warn("⚠️  El directorio $label no existe: {$path}");
            return;
        }

        $deleted = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile()) {
                if (@unlink($item->getPathname())) {
                    $deleted++;
                }
            }
        }

        $this->info("🗑️  Se eliminaron {$deleted} {$label} en {$path}.");
    }
}

