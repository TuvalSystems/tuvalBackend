<?php

// Old model class names can still appear in serialized jobs and persisted data.
// Register aliases lazily, without loading every model when Composer starts.
spl_autoload_register(static function (string $class): void {
    static $aliases;
    $aliases ??= array_change_key_case(require __DIR__ . '/legacy_model_namespaces.php', CASE_LOWER);
    $target = $aliases[strtolower(ltrim($class, '\\'))] ?? null;

    if ($target === null || !class_exists($target)) {
        return;
    }

    // PHP already treats names that differ only in case as the same class.
    if (strcasecmp($class, $target) !== 0) {
        class_alias($target, $class);
    }
}, true, true);
