<?php

namespace App\Services\Bodegas;

use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;

class DeliveryNoteDispatchService implements DeliveryNoteDispatchServiceInterface
{

    public function __construct(protected readonly DeliveryNoteCommonServiceInterface $common)
    {
    }


    /**
     * PROCESO DE CREACION DE GUIA DE DESPACHO
     */

    public function createDeliveryNoteFromDispatch($payload){
        return $payload;
    }
}
