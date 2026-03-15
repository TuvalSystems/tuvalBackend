<?php

namespace App\Enums\bodegas\guiaremision;

enum TransactionType: string
{
    // ENTRADAS (I)
    case DEV = 'DEV';
    case RTC = 'RTC';
    case RTD = 'RTD';
    case RTR = 'RTR';

        // SALIDAS (E)
    case DSP = 'DSP';
    case TRC = 'TRC';
    case TRD = 'TRD';
    case TRR = 'TRR';

    /**
     * Retorna 'I' para Ingresos y 'E' para Egresos
     */
    public function type(): string
    {
        return match ($this) {
            // Grupo de Entradas
            self::DEV,
            self::RTC,
            self::RTD,
            self::RTR => 'I',

            // Grupo de Salidas
            self::DSP,
            self::TRC,
            self::TRD,
            self::TRR => 'E',
        };
    }

    /**
     * Helper para saber si es una entrada
     */
    public function isIngreso(): bool
    {
        return $this->type() === 'I';
    }

    /**
     * Helper para saber si es una salida
     */
    public function isEgreso(): bool
    {
        return $this->type() === 'E';
    }
}
