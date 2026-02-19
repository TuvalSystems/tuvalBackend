<?php

namespace App\Enums\bodegas\guiaremision;

enum DeliveryNoteStatus: string
{
    case PENDIENTE = 'pendiente';
    case ENVIADA = 'enviada';
    case RECIBIDA = 'recibida';
    case ELIMINADA = 'eliminada';
}
