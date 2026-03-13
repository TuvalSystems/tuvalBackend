<?php
namespace App\Enums\bodegas\guiaremision;
enum TransactionDirection: string
{
    case INGRESO = 'I';
    case EGRESO  = 'E';

    public function label(): string
    {
        return match($this) {
            self::INGRESO => 'Ingreso',
            self::EGRESO  => 'Egreso',
        };
    }
}
