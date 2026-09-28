<?php

namespace App\Supports;

final class Money
{
    public static function normalize(
        int|float|string|null $value,
        int $decimals = 2
    ): float {
        if ($value === null || trim((string) $value) === '') {
            return 0.00;
        }

        return round((float) $value, $decimals);
    }
}
