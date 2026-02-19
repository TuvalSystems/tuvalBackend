<?php

namespace App\Enums\bodegas\guiaremision;


enum ReasonForTransfer: string
{
    case VENTA = 'Venta';
    case TRANSFERENCIA = 'Transferencia';
    case TRANSFERENCIA_RESERVA = 'transferencia_reserva';
}
