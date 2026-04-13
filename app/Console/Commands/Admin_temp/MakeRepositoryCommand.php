<?php

namespace App\Console\Commands\Admin_temp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeRepositoryCommand extends Command
{

    protected $signature = 'make:repository {name} {--i|interface}';


    protected $description = 'Crea un nuevo Repository en app/Repositories/{Modulo}/NombreRepository.php';


    public function handle()
    {
        $name = ltrim($this->argument('name'), '/\\');
        $withInterface = $this->option('interface');

        $parts = explode('/', str_replace('\\', '/', $name));
        $className = array_pop($parts);

        // Asegurar que el nombre termine en Repository
        if (!str_ends_with($className, 'Repository')) {
            $className .= 'Repository';
        }

        $namespace = 'App\\Repositories' . (count($parts) ? '\\' . implode('\\', $parts) : '');
        $directory = app_path('Repositories/' . implode('/', $parts));

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $repoPath = $directory . '/' . $className . '.php';
        $interfaceName = $className . 'Interface';

        if (File::exists($repoPath)) {
            $this->error("El repositorio ya existe!");
            return;
        }

        // Contenido del Repositorio (Sin sangrías al inicio)
        $repoContent = "<?php

namespace {$namespace};

" . ($withInterface ? "use {$namespace}\\Contracts\\{$interfaceName};\n\n" : "") . "class {$className} " . ($withInterface ? "implements {$interfaceName}" : "") . "
{
    public function __construct()
    {
        // Inyectar el Modelo si es necesario
    }
}
";

        File::put($repoPath, $repoContent);
        $this->info("✅ Repositorio creado: {$repoPath}");

        if ($withInterface) {
            $this->createInterface($directory, $namespace, $interfaceName);
        }
    }

    protected function createInterface($directory, $namespace, $interfaceName)
    {
        $interfaceDir = $directory . '/Contracts';
        if (!File::exists($interfaceDir)) {
            File::makeDirectory($interfaceDir, 0755, true);
        }

        $path = $interfaceDir . '/' . $interfaceName . '.php';
        $content = "<?php

namespace {$namespace}\\Contracts;

interface {$interfaceName}
{
    // Define los métodos de consulta
}
";
        File::put($path, $content);
        $this->info("📄 Interfaz creada: {$path}");
    }
}
