<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Models\GpCaja;
use App\Models\GpRelacionPuntoVentaCaja;
use App\Models\GpRelacionUsuarioCaja;
use App\Models\GpUsuario;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;

class CollectionRegistrationService implements CollectionRegistrationServiceInterface
{
    public function __construct(
        protected readonly CollectionRegistrationBuilderInterface $collectBuilder
    ) {}

    public function registerCollection(array $collectionData, GpUsuario $user): array
    {
        $boxId      = data_get($collectionData, 'caja_id');
        $pvId       = data_get($collectionData, 'pv_id');
        $pvCode     = data_get($collectionData, 'pvCodigo');
        $boxed      = GpCaja::select(['id', 'CAJA_CODIGO as cajaCodigo'])->where('id', $boxId)->where('estado', 1)->first();

        if (!$boxed) {
            throw new \Exception("La caja con ID {$boxId} no existe o no está activa.");
        }

        $existingRelation = GpRelacionPuntoVentaCaja::where('caja_id', $boxId)
            ->where('pv_id', $pvId)
            ->where('estado', 1)
            ->exists();

        if (!$existingRelation) {
            throw new \Exception("La caja {$boxed->CAJA_CODIGO}no está asociada al punto de venta con ID {$pvCode}.");
        }
        $userBoxed = GpRelacionUsuarioCaja::where('caja_id', $boxId)->where('estado', 1)->first();
        if (!$userBoxed) {
            throw new \Exception("El usuario  no tiene acceso a la caja con ID {$boxed->CAJA_CODIGO}.");
        }




        return [
            'data' => $collectionData, // Puedes incluir los datos del cobro registrado
            'usuario' => $user,
            'caja' => $boxed,
        ];
    }
}
