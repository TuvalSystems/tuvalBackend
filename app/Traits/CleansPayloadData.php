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
}
