<?php

namespace App\Services\Bodegas;

use App\Enums\EstablishmentGuideSequential;
use App\Models\despachos\GpGuiaRemision;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use Illuminate\Support\Collection;

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



    public function groupItemsByOrderReservation(Collection $details): array
    {

        $groups = [];
        foreach ($details as $det) {
            $trr = $det->traslado;
            if (!$trr || !$trr->cotizacion) {
                continue;
            }
            $cot = $trr->cotizacion->cotizacion;
            $groups[$cot] ??= ['cotizacion' => $cot, 'items' => [], 'ws_items' => []];

            $groups[$cot]['items'][] = $det;

            //items para enviar al webservice de la guia de reserva
            $groups[$cot]['ws_items'][] = [
                'item' => $det->art_code,
                'compartimiento' => '',
                'cantidad' => (float) $det->cantidad,
            ];
        }
        return $groups;
    }




    public function moveReservedToTransferredForTrfvLine($trl, $qty)
    {
        $cantidad = (float) ($trl->cantidad ?? 0);
        $trasladado = (float) ($trl->trasladado ?? 0);
        $reservado = (float) ($trl->reservado ?? 0);

        $restante = max(0.0, $cantidad - $trasladado);
        $delta = max(0.0, min((float) $qty, $restante));
        if ($delta <= 0) {
            $trl->completado = (int) (($trasladado) >= $cantidad);
            $trl->save();
            return;
        }
        $nuevoTrasladado = min($cantidad, $trasladado + $delta);
        $nuevoReservado = max(0.0, $reservado - $delta);

        $trl->trasladado = $nuevoTrasladado;
        $trl->reservado = $nuevoReservado;
        $trl->completado = (int) (($nuevoTrasladado) >= $cantidad);
        $trl->save();
    }

}
