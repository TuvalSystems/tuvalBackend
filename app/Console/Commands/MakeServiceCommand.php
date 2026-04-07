<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name} {--i|interface}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuevo Service en app/Services/{Modulo}/NombreService.php';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $name = ltrim($this->argument('name'), '/\\');
        $withInterface = $this->option('interface');


        $parts = explode('/', str_replace('\\', '/', $name));
        $className = array_pop($parts);
        $namespace = 'App\\Services' . (count($parts) ? '\\' . implode('\\', $parts) : '');
        $directory = app_path('Services/' . implode('/', $parts));

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $servicePath = $directory . '/' . $className . '.php';
        $interfacePath = $directory . '/Contracts/' . $className . 'Interface.php';


        // Evita sobreescritura
        if (File::exists($servicePath)) {
            $this->error("El servicio ya existe en: {$servicePath}");
            return;
        }

        // Contenido del Service
        $serviceNamespace = $namespace;
        $interfaceName = $className . 'Interface';
        $implements = $withInterface ? "implements {$interfaceName}" : '';
        $interfaceUse = $withInterface ? "use {$serviceNamespace}\\Contracts\\{$interfaceName};" : '';

$serviceContent = "<?php
namespace {$serviceNamespace};

" . ($withInterface ? "use {$serviceNamespace}\\Contracts\\{$interfaceName};\n\n" : "") . "class {$className} " . ($withInterface ? "implements {$interfaceName}" : "") . "
{
    public function __construct()
    {
        // Inyecta dependencias
    }
}
";

        FILE::put($servicePath, $serviceContent);
        $this->info("Servicio creado en: {$servicePath}");
        //crear interface si se pidio
        if ($withInterface) {
            $interfaceDir = $directory . '/Contracts';
            if (!File::exists($interfaceDir)) {
                File::makeDirectory($interfaceDir, 0755, true);
            }
$interfaceContent = "<?php
namespace {$serviceNamespace}\\Contracts;

interface {$interfaceName}
{
    // Define los métodos
}
";

            File::put($interfacePath, $interfaceContent);
            $this->info("📄 Interfaz creada: {$interfacePath}");
        }
    }
}
