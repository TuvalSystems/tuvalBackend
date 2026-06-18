<?php

namespace App\Traits;

trait CleansPayloadData
{
    protected function clean(array $data, string $key, string $default = ''): string
    {
        $value = data_get($data, $key, $default);

        if ($value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    protected function cleanLower(array $data, string $key, string $default = ''): string
    {
        return mb_strtolower($this->clean($data, $key, $default), 'UTF-8');
    }

    protected function cleanNullable(array $data, string $key): ?string
    {
        $value = data_get($data, $key);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function cleanInt(array $data, string $key, int $default = 0): int
    {
        $value = data_get($data, $key, $default);

        if ($value === null || $value === '' || $value === 'null' || $value === 'undefined') {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function cleanFloat(array $data, string $key, float $default = 0): float
    {
        $value = data_get($data, $key, $default);

        if ($value === null || $value === '' || $value === 'null' || $value === 'undefined') {
            return $default;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    protected function cleanNullableInt(array $data, string $key): ?int
    {
        $value = data_get($data, $key);

        if ($value === null || $value === '' || $value === 'null' || $value === 'undefined') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function cleanNullableFloat(array $data, string $key): ?float
    {
        $value = data_get($data, $key);

        if ($value === null || $value === '' || $value === 'null' || $value === 'undefined') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
