<?php

namespace App\Enums\bodegas;

enum AbcSegment: string
{

    case AR = 'AR';
    case BR = 'BR';
    case CR = 'CR';
    case R  = 'R';
    case RR = 'RR';
    case RX = 'RX';


    /** verifica si un string pertenece a los segmentos que debe resaltarse */

    public static function shouldHighlight(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        return self::tryFrom(strtoupper(trim($value))) !== null;
    }

    /** retorna todos los valores en un array simple */

    public static function allValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
