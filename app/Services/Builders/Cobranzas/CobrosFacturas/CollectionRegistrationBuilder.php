<?php

namespace App\Services\Builders\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectionStatusEnum;
use App\Models\Cobranzas\GpCobro;
use App\Models\GpCaja;
use App\Models\GpUsuario;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use Carbon\Carbon;

class CollectionRegistrationBuilder implements CollectionRegistrationBuilderInterface
{


    public function createHeaderCollection(array $collectionData, int $userBoxedId, GpCaja $boxed): GpCobro
    {
        $collection = new GpCobro();
        $collection->fill([
            'user_id'       => $userBoxedId,
            'pv_id'         => data_get($collectionData, 'pv_id'),
            'cliente_id'    => data_get($collectionData, 'cliente_id'),
            'caja_id'       => $boxed->id,
            'caja'          => $boxed->cajaCodigo,
            'fecha'         => Carbon::now()->toDateTimeString(),
            'estado'        => CollectionStatusEnum::INICIADO->value,
            'pedido'        => data_get($collectionData, 'pedido'),
            'referencia'    => data_get($collectionData, 'referencia'),
        ]);
        $collection->save();
        return $collection->refresh();
    }
}
