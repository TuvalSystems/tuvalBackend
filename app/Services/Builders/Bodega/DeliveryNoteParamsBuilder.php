<?php

namespace App\Services\Builders\Bodega;

use App\Models\despachos\GpGuiaRemision;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;

class DeliveryNoteParamsBuilder implements DeliveryNoteParamsBuilderInterface
{

    public function buildParamsHeaderTransfer(GpGuiaRemision $guia, string $pedido, array $items,  $user): array
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $pedido,
            'bodegaOrigen' => $guia->bodega_origen,
            'bodegaDestino' => $guia->bodega_destino,
            'observacion' => $guia->GUI_REM_OBSERVACION,
            'usuario' => $user->USERID ?? 'adminWeb',
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }
}
