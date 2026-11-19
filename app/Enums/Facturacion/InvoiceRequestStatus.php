<?php

namespace App\Enums\Facturacion;

enum InvoiceRequestStatus: string
{
    case NEW = 'nueva';
    case PENDING = 'pendiente';
    case PROCESSING = 'procesando';
    case COMPLETED = 'terminada';
    case ERROR = 'error';
}
