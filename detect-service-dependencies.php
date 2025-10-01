<?php


$servicesPath = __DIR__ . '/app/Services';
$serviceGraph = [];

function findPhpFiles($dir) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];

    foreach ($rii as $file) {
        if (!$file->isDir() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function extractClassName($code) {
    if (preg_match('/namespace\s+(.+);/', $code, $nsMatch) &&
        preg_match('/class\s+(\w+)/', $code, $classMatch)) {
        return trim($nsMatch[1]) . '\\' . trim($classMatch[1]);
    }
    return null;
}

function extractDependencies($code) {
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

function detectCycle($graph) {
    $visited = [];
    $stack = [];

    $hasCycle = false;

    $visit = function($node) use (&$visited, &$stack, &$graph, &$visit, &$hasCycle) {
        if (isset($stack[$node])) {
            echo "🔁 Cycle detected: $node is already in the stack\n";
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

// Build the dependency graph
$files = findPhpFiles($servicesPath);

foreach ($files as $file) {
    $code = file_get_contents($file);
    $className = extractClassName($code);
    $dependencies = extractDependencies($code);

    if ($className) {
        $serviceGraph[$className] = $dependencies;
    }
}

// Show graph
echo "📦 Service dependency graph:\n";
foreach ($serviceGraph as $service => $deps) {
    echo "- $service depends on:\n";
    foreach ($deps as $dep) {
        echo "  • $dep\n";
    }
}

echo "\n🔍 Checking for circular dependencies...\n";
$hasCycle = detectCycle($serviceGraph);

if (!$hasCycle) {
    echo "✅ No circular dependencies detected.\n";
}
