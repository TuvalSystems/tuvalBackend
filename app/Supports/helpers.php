<?php

if (! function_exists('format_money')) {
    function format_money(mixed $value, string $symbol = '$', int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }
        $number = is_numeric($value) ? (float) $value : 0;
        return $symbol . number_format($number, $decimals, '.', ',');
    }
}
