<?php

namespace App\Enums;

enum EstablishmentGuideSequential: string
{
    case INMACONSA     = '006';
    case CALIFORNIA    = '005';
    case CUENCA        = '008';
    case MANTA         = '009';
    case SANTO_DOMINGO = '010';
    case VIA_A_DAULE   = '011';
    case DURAN         = '012';
    case QUITO_NORTE   = '013';
    case QUITO_SUR     = '014';

    public function baseSequential(): string
    {
        return match ($this) {
            self::INMACONSA     => '000136549',
            self::CALIFORNIA    => '000267752',
            self::CUENCA        => '000052289',
            self::MANTA         => '000075395',
            self::SANTO_DOMINGO => '000076759',
            self::VIA_A_DAULE   => '000156517',
            self::DURAN         => '000049336',
            self::QUITO_NORTE   => '000127318',
            self::QUITO_SUR     => '000023955',
        };
    }

    /**
     * Para convertir "006" -> enum, o null si no existe.
     */
    public static function tryFromCode(string $code): ?self
    {
        return match ($code) {
            self::INMACONSA->value     => self::INMACONSA,
            self::CALIFORNIA->value    => self::CALIFORNIA,
            self::CUENCA->value        => self::CUENCA,
            self::MANTA->value         => self::MANTA,
            self::SANTO_DOMINGO->value => self::SANTO_DOMINGO,
            self::VIA_A_DAULE->value   => self::VIA_A_DAULE,
            self::DURAN->value         => self::DURAN,
            self::QUITO_NORTE->value   => self::QUITO_NORTE,
            self::QUITO_SUR->value     => self::QUITO_SUR,
            default                    => null,
        };
    }
}
