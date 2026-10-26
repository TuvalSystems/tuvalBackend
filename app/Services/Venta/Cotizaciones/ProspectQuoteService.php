<?php

namespace App\Services\Venta\Cotizaciones;

use App\Models\Ventas\Clientes\GpProspecto;
use App\Services\Venta\Cotizaciones\Contracts\ProspectQuoteServiceInterface;

class ProspectQuoteService implements ProspectQuoteServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }


    public function restoreProspectQuote(string $ruc): array
    {
        $pr = GpProspecto::where('CLI_RUC', $ruc)->first();
        return [
            'id'                => $pr->id,
            'identificacion'    => $pr->CLI_RUC,
            'razon_social'      => $pr->CLI_RAZON_SOCIAL,
            'correo_sri'        => $pr->CLI_CORREO_ELECT_SRI,
            'telefono1'         => $pr->CLI_TELEFONO_1,
            'direcciones'       =>  [
                [
                'region_id'     => null,
                'provincia_id'  => null,
                'canton_id'     => null,
                'direccion'     =>  $pr->CLI_DIRECCION,
                'provinces'     =>  [],
                'cantons'       => [],
            ]
            ]
        ];
    }
}
