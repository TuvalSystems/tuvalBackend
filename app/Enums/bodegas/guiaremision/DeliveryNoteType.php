<?php

namespace   App\Enums\bodegas\guiaremision;

enum DeliveryNoteType: string
{
    case DESPACHO = 'despacho';
    case REPOSICION = 'reposicion';
    case TRANSFERENCIA = 'transferencia';
}
