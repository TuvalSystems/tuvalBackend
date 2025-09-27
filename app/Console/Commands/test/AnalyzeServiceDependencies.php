<?php

namespace App\Console\Commands\test;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AnalyzeServiceDependencies extends Command
{
       protected $signature = 'services:analyze-dependencies';
    protected $description = 'Analiza dependencias entre servicios y detecta ciclos.';

    public function handle()
    {
        $this->info("🔍 Analizando dependencias entre servicios...");

        $servicesPath = app_path('Services');
        $serviceGraph = [];

        foreach ($this->findPhpFiles($servicesPath) as $file) {
            $code = file_get_contents($file);
            $className = $this->extractClassName($code);
            $dependencies = $this->extractDependencies($code);

            if ($className) {
                $serviceGraph[$className] = $dependencies;
            }
        }

        foreach ($serviceGraph as $service => $deps) {
            $this->line("- $service:");
            foreach ($deps as $dep) {
                $this->line("  • $dep");
            }
        }

        $this->warn("\n🚨 Verificando ciclos...");
        if ($this->detectCycle($serviceGraph)) {
            $this->error("🔁 Ciclo detectado.");
        } else {
            $this->info("✅ No hay ciclos detectados.");
        }

        $dotPath = base_path('service-dependencies.dot');
        $this->generateDotFile($serviceGraph, $dotPath);
        $this->info("📄 Archivo .dot generado: $dotPath");
        $this->line("👉 Ejecuta: dot -Tpng service-dependencies.dot -o service-dependencies.png");
    }

    private function findPhpFiles($dir)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $files = [];

        foreach ($rii as $file) {
            if (!$file->isDir() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function extractClassName($code)
    {
        if (preg_match('/namespace\s+(.+);/', $code, $nsMatch) &&
            preg_match('/class\s+(\w+)/', $code, $classMatch)) {
            return trim($nsMatch[1]) . '\\' . trim($classMatch[1]);
        }

        return null;
    }

    private function extractDependencies($code)
    {
        if (preg_match('/__construct\s*\(([^)]*)\)/s', $code, $match)) {
            $params = explode(',', $match[1]);
            $dependencies = [];

            foreach ($params as $param) {
                if (preg_match('/([a-zA-Z0-9_\\\]+)\s+\$[a-zA-Z_]+/', trim($param), $typeMatch)) {
                    $dependencies[] = trim($typeMatch[1]);
                }
            }

            return $dependencies;
        }

        return [];
    }

    private function detectCycle(array $graph): bool
    {
        $visited = [];
        $stack = [];
        $hasCycle = false;

        $visit = function ($node) use (&$visited, &$stack, &$graph, &$visit, &$hasCycle) {
            if (isset($stack[$node])) {
                $this->error("🔁 Ciclo detectado: $node está en el stack.");
                $hasCycle = true;
                return;
            }

            if (isset($visited[$node])) return;

            $visited[$node] = true;
            $stack[$node] = true;

            foreach ($graph[$node] ?? [] as $dep) {
                $visit($dep);
            }

            unset($stack[$node]);
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return $hasCycle;
    }

    private function generateDotFile(array $graph, string $outputPath)
    {
        $dot = "digraph ServiceDependencies {\n";
        $dot .= "    node [shape=box, fontname=\"Arial\"];\n";

        foreach ($graph as $service => $deps) {
            $serviceNode = str_replace('\\', '\\\\', $service);
            foreach ($deps as $dep) {
                $depNode = str_replace('\\', '\\\\', $dep);
                $dot .= "    \"$serviceNode\" -> \"$depNode\";\n";
            }
        }

        $dot .= "}\n";

        file_put_contents($outputPath, $dot);
    }
}
