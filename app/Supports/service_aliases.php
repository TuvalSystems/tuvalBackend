<?php

// Resolve historical names lazily for serialized jobs and older integrations.
spl_autoload_register(static function (string $class): void {
    static $aliases;
    $aliases ??= array_change_key_case(require __DIR__ . '/legacy_service_namespaces.php', CASE_LOWER);
    $target = $aliases[strtolower(ltrim($class, '\\'))] ?? null;

    if ($target === null || (!class_exists($target) && !interface_exists($target))) {
        return;
    }

    if (strcasecmp($class, $target) !== 0) {
        class_alias($target, $class);
    }
}, true, true);
