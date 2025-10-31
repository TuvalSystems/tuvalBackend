<?php

namespace App\Services\Bodegas;

use App\Enums\EstablishmentGuideSequential;
use App\Models\despachos\GpGuiaRemision;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;

class DeliveryNoteCommonService implements DeliveryNoteCommonServiceInterface
{


    public function getNextGuideSequential(string $establecimiento, string $ptoEmision): string
    {
        //1.- ULTIMA DE GUIAS NORMALES
        $lastNormal = GpGuiaRemision::where('GUI_REM_ESTABLECIMIENTO', $establecimiento)
            ->where('GUI_REM_PTO_EMISION', $ptoEmision)
            ->max('GUI_REM_SECUENCIAL');

        //2.- ULTIMA DE GUIAS DE RESERVAS
        $lastReservation = GpGuiaRemisionReserva::where('establecimiento', $establecimiento)
            ->where('pto_emision', $ptoEmision)
            ->max('secuencial');

        $n1 = $this->toSequentialInt($lastNormal);
        $n2 = $this->toSequentialInt($lastReservation);

        $current = max($n1, $n2);
        if ($current === 0) {
            $current = $this->getBaseSequentialByEstablishment($establecimiento);
        }
        return str_pad($current + 1, 9, '0', STR_PAD_LEFT);
    }


    public function toSequentialInt(?string $value): int
    {
        if (empty($value)) {
            return 0;
        }
        return (int) ltrim($value, '0');
    }


    public function getBaseSequentialByEstablishment(string $establecimiento): int
    {
        $enum = EstablishmentGuideSequential::tryFromCode($establecimiento);
        $base = $enum?->baseSequential() ?? '000000';
        return (int) ltrim($base, '0');
    }
}
