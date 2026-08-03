<?php

namespace App\Enums\Cobranzas\CobrosFacturas;


enum CollectionStatusEnum : string
{
    case INICIADO = 'iniciado';
    case REVISADO = 'revisado';
    case ENVIADO = 'enviado';
    case NO_ENVIADO = 'no_enviado';
    case RECHAZADO = 'rechazado';
}
